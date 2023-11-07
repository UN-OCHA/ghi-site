<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
use Drupal\hpc_api\Helpers\ApiEntityHelper;

/**
 * Abstraction class for API plan entity objects.
 */
class PlanEntity extends EntityObjectBase {

  use PlanVersionArgument;

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $entity = $this->getRawData();
    $entity_version = $this->getEntityVersion();
    $prototype = $entity?->entityPrototype;

    return (object) [
      'id' => $entity?->id,
      'name' => $prototype ? $prototype->value->name->en->singular : NULL,
      'plural_name' => $prototype ? $prototype->value->name->en->plural : NULL,
      'group_name' => $prototype ? $prototype->value->name->en->plural : NULL,
      'display_name' => $prototype ? ($prototype->value->name->en->singular . ' ' . $entity_version?->customReference) : NULL,
      'description' => $entity_version?->value?->description,
      // Need to cast to array until HPC-6440 is fixed.
      'support' => !empty($entity_version->value->support) ? (array) $entity_version->value->support : NULL,
      'ref_code' => $prototype ? $prototype->refCode : NULL,
      'entity_type' => $prototype ? $prototype->type : NULL,
      'entity_prototype_id' => $prototype ? $prototype->id : NULL,
      'order_number' => $prototype ? $prototype->orderNumber : NULL,
      'parent_id' => $this->getParentId(),
      'governing_entity_parent_id' => $entity->parentId ?? NULL,
      'root_parent_id' => $this->getMainLevelParentId(),
      'custom_reference' => $entity_version?->customReference,
      'composed_reference' => $this->getComposedReference(),
      'sort_key' => $prototype ? ($prototype->orderNumber . $entity_version?->customReference) : NULL,
      'tags' => $entity_version?->tags ?? [],

      // Legacy support.
      'custom_id' => $entity_version?->customReference,
    ];
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
   *   The plan entity parents.
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
    return array_filter(array_map(function ($entity_id) {
      return PlanEntityHelper::getPlanEntity($entity_id, $this->getPlanVersionArgument());
    }, $first_ref->planEntityIds));
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
   * Get the main level parent id.
   *
   * @return int
   *   The id of the main parent.
   */
  public function getMainLevelParentId() {
    $entity = $this->getRawData();
    if (!$entity) {
      return NULL;
    }
    $entity_version = $this->getEntityVersion($entity);
    if (in_array($entity->entityPrototype->refCode, ApiEntityHelper::MAIN_LEVEL_PLE_REF_CODES) || empty($entity_version->value->support)) {
      return NULL;
    }
    $support = $entity_version?->value?->support;
    $support = is_array($support) ? reset($support) : $support;
    $plan_entity_ids = $support?->planEntityIds ?? [];
    return reset($plan_entity_ids);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityVersion() {
    return $this->getRawData()?->planEntityVersion;
  }

  /**
   * Get the composed reference for a plan entity object.
   *
   * @return string
   *   The composed reference string.
   */
  protected function getComposedReference() {
    $entity = $this->getRawData();
    if (!$entity) {
      return '';
    }
    if (property_exists($entity, 'composedReference')) {
      return $entity->composedReference;
    }
    $prototype = $entity->entityPrototype;
    return $prototype->refCode . $this->getEntityVersion()->customReference;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityName() {
    return $this->display_name;
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
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity
   *   The parent governing entity if found.
   */
  private function getParentGoverningEntity() {
    $entity_id = $this->governing_entity_parent_id ?? NULL;
    if (!$entity_id) {
      return NULL;
    }
    $entity = PlanEntityHelper::getGoverningEntity($entity_id, $this->getPlanVersionArgument());
    return $entity instanceof GoverningEntity ? $entity : NULL;
  }

  /**
   * Get the version argument to use for this entity.
   *
   * @return string
   *   The version argument as a string.
   */
  private function getPlanVersionArgument() {
    return $this->getPlanVersionArgumentForPlanId($this->getPlanId());
  }

}
