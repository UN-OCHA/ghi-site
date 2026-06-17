<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'governing_entity' fabric query.
 */
#[FabricQuery(
  id: 'governing_entity',
  label: new TranslatableMarkup('Governing entity query'),
)]
class GoverningEntityQuery extends FabricQueryBase {

  use PlanQueryTrait;

  /**
   * Get a governing entity by id.
   *
   * @param int $entity_id
   *   The governing entity id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity|null
   *   The governing entity object or NULL if not found.
   */
  public function getGoverningEntity(int $entity_id): ?GoverningEntity {
    $entities = $this->getGoverningEntitiesById([$entity_id]);
    return !empty($entities) ? reset($entities) : NULL;
  }

  /**
   * Get governing entities by id.
   *
   * @param int[] $entity_ids
   *   The governing entity ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity[]
   *   An array of governing entity objects.
   */
  public function getGoverningEntitiesById(array $entity_ids): array {
    $entity_ids = array_unique($entity_ids);
    $governing_entities = $this->objectStore->getObjects($entity_ids, GoverningEntity::getObjectStorageKey());
    if (count($governing_entities) == count($entity_ids)) {
      return $governing_entities;
    }
    $entity_ids = array_diff($entity_ids, array_keys($governing_entities));

    if (count($entity_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      // We need to do multiple queries.
      return $this->doChunkedQuery($entity_ids, fn ($ids): array => $this->getGoverningEntitiesById($ids));
    }

    // Get the governing entity.
    $items = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
      ->setFilters([
        'Id' => $entity_ids,
      ])
      ->execute() ?: [];

    $governing_entities = $this->buildResultObjects($items, GoverningEntity::class);
    $this->objectStore->addObjects($governing_entities);
    return $governing_entities;
  }

  /**
   * Get plan entities by plan id.
   *
   * @param int $plan_id
   *   The plan entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity[]
   *   An array of governing entities.
   */
  public function getGoverningEntitiesByPlanId(int $plan_id): array {
    $entities = $this->objectStore->getObjectCollection(GoverningEntity::getObjectStorageKey(), 'PlanId', $plan_id);
    if (!empty($entities)) {
      return $entities;
    }
    // Query entities.
    $items = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
      ->setFilters([
        'PlanId' => $plan_id,
      ])
      ->execute() ?: [];

    $entities = $this->buildResultObjects($items, GoverningEntity::class);
    $this->objectStore->addObjectCollection($entities, GoverningEntity::getObjectStorageKey(), 'PlanId');
    return $entities;
  }

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param int $plan_id
   *   The plan id to query.
   * @param string $cluster_tag
   *   The cluster tag.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity[]
   *   An array of governing entity objects.
   */
  public function getTaggedClustersForPlan(int $plan_id, string $cluster_tag): array {
    $items = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
      ->setFilters([
        'PlanId' => $plan_id,
      ])
      ->execute() ?: [];
    $governing_entities = $this->buildResultObjects($items, GoverningEntity::class);
    $this->objectStore->addObjects($governing_entities);
    $governing_entities = array_filter($governing_entities, fn ($entity) => in_array($cluster_tag, $entity->getTags()));
    return $governing_entities;
  }

}
