<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity as EntityGoverningEntity;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'plan_entity' fabric query.
 */
#[FabricQuery(
  id: 'plan_entity',
  label: new TranslatableMarkup('Plan entity query'),
)]
class PlanEntityQuery extends FabricQueryBase {

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
  public function getEntity($entity_type, $entity_id): ?PlanEntityInterface {
    switch ($entity_type) {
      case 'governingEntity':
        $items = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems(), NULL, 1)
          ->setFilters([
            'Id' => $entity_id,
            'RecordStatus' => 'Active',
          ])
          ->execute();
        $objects = $this->buildResultObjects($items, GoverningEntity::class);
        return count($objects) == 1 ? reset($objects) : NULL;

      case 'planEntity':
        $items = $this->fabricClient->createQuery('logframeEntities', PlanEntity::getGraphQlItems(), NULL, 1)
          ->setFilters([
            'Id' => $entity_id,
            'RecordStatus' => 'Active',
          ])
          ->execute();
        $objects = $this->buildResultObjects($items, PlanEntity::class);
        return count($objects) == 1 ? reset($objects) : NULL;
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
  public function getEntities($entity_type, $entity_ids): array {
    if (count($entity_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      // We need to do multiple queries.
      return $this->doChunkedQuery($entity_ids, fn ($ids): array => $this->getEntities($entity_type, $ids));
    }
    switch ($entity_type) {
      case 'governingEntity':
        $items = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
          ->setFilters([
            'Id' => $entity_ids,
            'RecordStatus' => 'Active',
          ])
          ->execute();
        return $this->buildResultObjects($items, GoverningEntity::class);

      case 'planEntity':
        $items = $this->fabricClient->createQuery('logframeEntities', PlanEntity::getGraphQlItems())
          ->setFilters([
            'Id' => $entity_ids,
            'RecordStatus' => 'Active',
          ])
          ->execute();
        return $this->buildResultObjects($items, GoverningEntity::class);
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
  public function getPlanEntities(int $plan_id, ?ContentEntityInterface $context_object = NULL, $entity_type = NULL, ?array $filters = NULL) {
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $plan_id,
      'id' => $context_object ? $context_object->id() : NULL,
      'entity_type' => $entity_type,
    ] + ($filters ?? [])));

    $plan_entities = $this->getCache($cache_key);
    if ($plan_entities) {
      return $plan_entities;
    }

    $fetch_coordination_entities = $entity_type === NULL || $entity_type == 'governing';
    $fetch_logframe_entities = $entity_type === NULL || $entity_type == 'plan';

    $query_filter = [
      'PlanId' => $plan_id,
      'RecordStatus' => 'Active',
    ];

    $queries = [];
    if ($fetch_coordination_entities) {
      $queries[] = $this->fabricClient->createQuery('coordinationEntities', GoverningEntity::getGraphQlItems())
        ->setFilters($query_filter);
    }
    if ($fetch_logframe_entities) {
      if ($context_object instanceof EntityGoverningEntity) {
        $query_filter['CoordinationEntityId'] = $context_object->getSourceId();
      }
      $queries[] = $this->fabricClient->createQuery('logframeEntities', PlanEntity::getGraphQlItems())
        ->setFilters($query_filter);
    }
    $data = $this->fabricClient->executeMultiple($queries);
    $coordination_entities = $this->buildResultObjects($data['coordinationEntities'] ?? [], GoverningEntity::class);
    $logframe_entities = $this->buildResultObjects($data['logframeEntities'] ?? [], PlanEntity::class);
    $plan_entities = $coordination_entities + $logframe_entities;

    // Apply filters.
    if (!empty($filters)) {
      $plan_entities = array_filter($plan_entities, fn($entity) => array_intersect_key($entity->toArray(), $filters) == $filters);
    }

    $this->setCache($cache_key, $plan_entities);
    return $plan_entities;
  }

}
