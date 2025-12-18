<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;

/**
 * Abstraction class for API plan entity objects.
 */
class PlanEntity extends EntityObjectBase {

  const GRAPHQL_ITEMS = "
    Id
    Name
    Description
    PlanId
    EntityTypeId
    CoordinationEntityId
    HpcEntityPrototypeId
    CustomReference
    ComposedReference
    SortOrder
  ";

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
      'singular_name' => implode(' ', array_filter([$prototype?->getNameSingular(), $data->CustomReference])),
      'plural_name' => $prototype?->getNamePlural(),
      'description' => $data->Name,
      // @codingStandardsIgnoreStart
      // @todo Retrieve and store the support information.
      // 'support' => !empty($_entity_version->value->support) ? (array) $_entity_version->value->support : NULL,
      // @codingStandardsIgnoreEnd
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
    if (property_exists($entity, 'parentId')) {
      return $entity->parentId;
    }
    $entity_version = $this->getEntityVersion($entity);
    if (empty($entity_version->value->support)) {
      return NULL;
    }
    $first_ref = reset($entity_version->value->support);
    if (!property_exists($first_ref, 'planEntityIds') || empty($first_ref->planEntityIds)) {
      return NULL;
    }
    return reset($first_ref->planEntityIds);
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
    $entity_version = $this->getEntityVersion($entity);
    if (empty($entity_version->value->support)) {
      return [];
    }
    if (!is_array($entity_version->value->support)) {
      return [];
    }
    $first_ref = reset($entity_version->value->support);
    if (property_exists($first_ref, 'planEntityIds') && !empty($first_ref->planEntityIds)) {
      return $first_ref->planEntityIds;
    }
    if (property_exists($entity, 'parentId')) {
      return [$entity->parentId];
    }
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
    $entity_version = $this->getEntityVersion($entity);
    if (empty($entity_version->value->support)) {
      return [];
    }
    $first_ref = reset($entity_version->value->support);
    if (!property_exists($first_ref, 'planEntityIds') || empty($first_ref->planEntityIds)) {
      return [];
    }
    $parents = [];
    foreach ($first_ref->planEntityIds as $entity_id) {
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
  public function getEntityVersion() {
    return $this->getRawData()->planEntityVersion ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName() {
    $parent_entity = $this->getParentGoverningEntity();
    if (!$parent_entity) {
      return $this->t('@type @custom_reference', [
        '@type' => $this->name,
        '@custom_reference' => $this->custom_reference,
      ]);
    }
    return $this->t('@parent: @type @custom_reference', [
      '@parent' => $parent_entity->custom_reference . ' ' . $parent_entity->entity_prototype_name,
      '@type' => $this->name,
      '@custom_reference' => $this->custom_reference,
    ]);
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
