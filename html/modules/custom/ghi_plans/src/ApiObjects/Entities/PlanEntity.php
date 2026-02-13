<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * Abstraction class for API plan entity objects.
 */
class PlanEntity extends EntityObjectBase {

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'Description',
    'PlanId',
    'EntityTypeId',
    'CoordinationEntityId',
    'HpcEntityPrototypeId',
    'CustomReference',
    'ComposedReference',
    'SortOrder',
    'logframeEntityLink { items { ParentLogframeEntityId } }',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    $prototype = !empty($data->HpcEntityPrototypeId ?? NULL) ? PlanEntityHelper::getEntityPrototype($data->HpcEntityPrototypeId) : NULL;
    return (object) [
      'id' => $data->Id,
      'name' => $prototype?->getNameSingular(),
      'group_name' => $prototype?->getNamePlural(),
      'display_name' => $prototype?->getNameSingular(),
      'singular_name' => $prototype?->getNameSingular(),
      'plural_name' => $prototype?->getNamePlural(),
      'description' => $data->Name,
      // phpcs:disable
      // @todo Retrieve and store the support information.
      // 'support' => !empty($_entity_version->value->support) ? (array) $_entity_version->value->support : NULL,
      // phpcs:enable
      'ref_code' => $prototype?->getRefCode(),
      'entity_type' => $prototype?->getType(),
      'entity_prototype_id' => $prototype?->id(),
      'order_number' => $data->SortOrder ?? $prototype?->getOrderNumber(),
      'parent_id' => $this->getParentId(),
      'governing_entity_parent_id' => $data->CoordinationEntityId ?? NULL,
      'custom_reference' => $data->CustomReference,
      'composed_reference' => $data->ComposedReference ?: ($prototype?->getRefCode() . $data->CustomReference),
      'sort_key' => $data->SortOrder ?? (($prototype?->getOrderNumber() ?? '') . ($data->CustomReference) ?? NULL),

      // Legacy support.
      'custom_id' => $data->CustomReference,
    ];
  }

  /**
   * Get the governing entity parent of an entity.
   *
   * @return int|null
   *   The id of the governing entity parent.
   */
  public function getGoverningEntityParentId(): ?int {
    return $this->governing_entity_parent_id;
  }

  /**
   * Get the direct parent of an entity.
   *
   * @return int
   *   The id of the direct parent.
   */
  public function getParentId() {
    $entity = $this->getRawData();
    if (!$entity) {
      return NULL;
    }
    $items = $entity->logframeEntityLink?->items ?? [];
    if (empty($items)) {
      return NULL;
    }
    return reset($items)->ParentLogframeEntityId;
  }

  /**
   * Get the parent ids of an entity.
   *
   * @return int[]
   *   The ids of the parents.
   */
  public function getParentIds() {
    $entity = $this->getRawData();
    if (!$entity) {
      return [];
    }
    $items = $entity->logframeEntityLink?->items ?? [];
    if (empty($items)) {
      return [];
    }
    return array_map(fn ($item) => $item->ParentLogframeEntityId, $items);
  }

  /**
   * Get the plan entity parents.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   The plan entity parents keyed by their entity ids.
   */
  public function getPlanEntityParents() {
    $entity = $this->getRawData();
    if (!$entity) {
      return [];
    }
    $parent_ids = $this->getParentIds();
    $parents = [];
    foreach ($parent_ids as $entity_id) {
      $parents[$entity_id] = PlanEntityHelper::getPlanEntity($entity_id);
    }
    return array_filter($parents);
  }

  /**
   * Get the name of the hierarchical group that this entity belongs to.
   *
   * @return string
   *   The group name, e.g. "Strategic Objectives".
   */
  public function getGroupName() {
    return $this->group_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    $parent_entity = $this->getParentGoverningEntity();
    if (!$parent_entity) {
      return $this->t('@type @custom_reference', [
        '@type' => $this->getName(),
        '@custom_reference' => $this->getCustomReference(),
      ]);
    }
    return $this->t('@parent: @type @custom_reference', [
      '@parent' => $parent_entity->getCustomReference() . ' ' . $parent_entity->getPrototypeName(),
      '@type' => $this->getName(),
      '@custom_reference' => $this->getCustomReference(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSingularName(): string {
    return $this->map->singular_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralName(): string {
    return $this->map->plural_name;
  }

  /**
   * {@inheritdoc}
   */
  public function getSortKey(): string|int {
    return $this->sort_key;
  }

  /**
   * Get the parent governing entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity|null
   *   The parent governing entity if found or NULL otherwise.
   */
  public function getParentGoverningEntity($recursion = FALSE): ?GoverningEntity {
    if ($entity_id = $this->governing_entity_parent_id ?? NULL) {
      $entity = PlanEntityHelper::getGoverningEntity($entity_id);
      return $entity instanceof GoverningEntity ? $entity : NULL;
    }
    if (!$recursion) {
      return NULL;
    }
    // Also look at the parents if requested.
    $parents = $this->getPlanEntityParents();
    foreach ($parents as $parent) {
      if ($entity = $parent->getParentGoverningEntity()) {
        return $entity;
      }
    }
    return NULL;
  }

}
