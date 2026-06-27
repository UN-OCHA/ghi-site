<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'logframe_entity' fabric query.
 */
#[FabricQuery(
  id: 'plan_entity',
  label: new TranslatableMarkup('Logframe entity query'),
)]
class PlanEntityQuery extends FabricQueryBase {

  use PlanQueryTrait;

  /**
   * Get a logframe entity by id.
   *
   * @param int $entity_id
   *   The logframe entity id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity|null
   *   The plan entity object or NULL if not found.
   */
  public function getPlanEntity(int $entity_id): ?PlanEntity {
    $entities = $this->getPlanEntitiesById([$entity_id]);
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * Get plan entities by id.
   *
   * @param int[] $entity_ids
   *   The plan entity ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   An array of plan entity objects.
   */
  public function getPlanEntitiesById(array $entity_ids): array {
    $entity_ids = array_unique($entity_ids);
    $entities = $this->objectStore->getObjects($entity_ids, PlanEntity::getObjectStorageKey());
    if (count($entities) == count($entity_ids)) {
      return $entities;
    }
    $entity_ids = array_diff($entity_ids, array_keys($entities));

    // Get the plan entity.
    $items = $this->fabricClient->createQuery('logframeEntities', PlanEntity::getGraphQlItems())
      ->setFilters([
        'Id' => $entity_ids,
      ])
      ->execute() ?: [];

    $entities = $this->buildResultObjects($items, PlanEntity::class);
    $this->objectStore->addObjects($entities);
    return $entities;
  }

  /**
   * Get plan entities by plan id.
   *
   * @param int $plan_id
   *   The plan entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\PlanEntity[]
   *   An array of plan entities.
   */
  public function getPlanEntitiesByPlanId(int $plan_id): array {
    $entities = $this->objectStore->getObjectCollection(PlanEntity::getObjectStorageKey(), 'PlanId', $plan_id);
    if (!empty($entities)) {
      return $entities;
    }
    // Query entities.
    $items = $this->fabricClient->createQuery('logframeEntities', PlanEntity::getGraphQlItems())
      ->setFilters([
        'PlanId' => $plan_id,
      ])
      ->execute() ?: [];
    $entities = $this->buildResultObjects($items, PlanEntity::class);
    $this->objectStore->addObjectCollection($entities, PlanEntity::getObjectStorageKey(), 'PlanId');
    return $entities;
  }

}
