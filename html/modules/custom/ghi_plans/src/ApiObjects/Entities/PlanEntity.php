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
    return (object) [
      'id' => $data->Id,
      'description' => $data->Name,
      // phpcs:disable
      // @todo Retrieve and store the support information.
      // 'support' => !empty($_entity_version->value->support) ? (array) $_entity_version->value->support : NULL,
      // phpcs:enable
      'parent_id' => $this->getParentId(),
      'governing_entity_parent_id' => $data->CoordinationEntityId ?? NULL,
      'custom_reference' => $data->CustomReference,

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
  public function getParentId(): ?int {
    $items = $this->getRawData()->logframeEntityLink?->items ?? [];
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
  public function getParentIds(): array {
    $items = $this->getRawData()->logframeEntityLink?->items ?? [];
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
    $parents = [];
    foreach ($this->getParentIds() as $entity_id) {
      $parents[$entity_id] = PlanEntityHelper::getPlanEntity($entity_id);
    }
    return array_filter($parents);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->getPrototype()?->getNameSingular() ?? $this->getDescription();
  }

  /**
   * {@inheritdoc}
   */
  public function getDisplayName(): ?string {
    return $this->getPrototype()?->getNameSingular() ?? NULL;
  }

  /**
   * Get the name of the hierarchical group that this entity belongs to.
   *
   * @return string
   *   The group name, e.g. "Strategic Objectives".
   */
  public function getGroupName(): ?string {
    return $this->getPrototype()?->getNamePlural();
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName(): string {
    $parent_entity = $this->getParentGoverningEntity();
    if (!$parent_entity) {
      return (string) $this->t('@type @custom_reference', [
        '@type' => $this->getName(),
        '@custom_reference' => $this->getCustomReference(),
      ]);
    }
    return (string) $this->t('@parent: @type @custom_reference', [
      '@parent' => $parent_entity->getCustomReference() . ' ' . $parent_entity->getPrototypeName(),
      '@type' => $this->getName(),
      '@custom_reference' => $this->getCustomReference(),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getComposedReference() {
    $data = $this->getRawData();
    return $data->ComposedReference ?: ($this->getEntityTypeRefCode() . $this->getCustomReference());
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderNumber(): ?int {
    return $this->getRawData()->SortOrder ?? parent::getOrderNumber();
  }

  /**
   * {@inheritdoc}
   */
  public function getSortKey(): ?string {
    return $this->getRawData()->SortOrder ?? parent::getSortKey();
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
