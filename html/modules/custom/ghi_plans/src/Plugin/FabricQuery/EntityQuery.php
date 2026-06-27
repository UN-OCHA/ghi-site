<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity as EntityGoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'entity' fabric query.
 */
#[FabricQuery(
  id: 'entity',
  label: new TranslatableMarkup('Plan entity query'),
)]
class EntityQuery extends FabricQueryBase {

  use PlanQueryTrait;

  /**
   * Get an entity by type and id.
   *
   * @param string $entity_type
   *   The entity type to query.
   * @param int $entity_id
   *   The entity id to query.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface
   *   The entity object.
   */
  public function getEntity(string $entity_type, int $entity_id): ?PlanEntityInterface {
    switch ($entity_type) {
      case 'governingEntity':
        return $this->getGoverningEntityQuery()->getGoverningEntity($entity_id);

      case 'planEntity':
        return $this->getPlanEntityQuery()->getPlanEntity($entity_id);
    }

    return NULL;
  }

  /**
   * Get an entity by type and id.
   *
   * @param string $entity_type
   *   The entity type to query.
   * @param int[] $entity_ids
   *   An array of entity ids to query.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[]
   *   An array of entity objects.
   */
  public function getEntities(string $entity_type, array $entity_ids): array {
    switch ($entity_type) {
      case 'governingEntity':
        return $this->getGoverningEntityQuery()->getGoverningEntitiesById($entity_ids);

      case 'planEntity':
        return $this->getPlanEntityQuery()->getPlanEntitiesById($entity_ids);
    }

    return [];
  }

  /**
   * Get available plan entities for the given context.
   *
   * @param int $plan_id
   *   The plan id.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param string $entity_type
   *   The entity type to restrict the context.
   * @param array $filters
   *   The optional aray with filter key value pairs.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]|null
   *   An array of plan entity objects for the given context or NULL.
   */
  public function getEntitiesForPlan(int $plan_id, ?ContentEntityInterface $context_object = NULL, $entity_type = NULL, ?array $filters = NULL): array {
    $fetch_coordination_entities = $entity_type === NULL || $entity_type == 'governing';
    $fetch_logframe_entities = $entity_type === NULL || $entity_type == 'plan';
    $context_object_id = $context_object instanceof EntityGoverningEntity ? $context_object->getSourceId() : NULL;

    $coordination_entities = $logframe_entities = [];
    if ($fetch_coordination_entities) {
      $coordination_entities = $this->getGoverningEntityQuery()->getGoverningEntitiesByPlanId($plan_id);
      // Filter by context.
      if ($context_object_id) {
        $_filters = ['id' => $context_object_id];
        $coordination_entities = array_filter($coordination_entities, fn($entity) => array_intersect_key($entity->toArray(), $_filters) == $_filters);
      }
    }
    if ($fetch_logframe_entities) {
      $logframe_entities = $this->getPlanEntityQuery()->getPlanEntitiesByPlanId($plan_id);
      // Filter by context.
      if ($context_object_id) {
        $_filters = ['governing_entity_parent_id' => $context_object_id];
        $logframe_entities = array_filter($logframe_entities, fn($entity) => array_intersect_key($entity->toArray(), $_filters) == $_filters);
      }
    }
    /** @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $plan_entities */
    $plan_entities = $coordination_entities + $logframe_entities;

    // Apply filters.
    if (!empty($filters)) {
      $plan_entities = array_filter($plan_entities, fn($entity) => array_intersect_key($entity->toArray(), $filters) == $filters);
    }
    return $plan_entities;
  }

}
