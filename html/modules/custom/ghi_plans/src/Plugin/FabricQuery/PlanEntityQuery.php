<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
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
        $payload = "
          coordinationEntities (filter: { Id: { eq: {$entity_id} } } ) {
            items { " . GoverningEntity::GRAPHQL_ITEMS . " }
          }";
        $data = $this->fabricQuery->query($payload);
        $items = $data ? $this->getItems($data, 'coordinationEntities') : [];
        return count($items) == 1 ? new GoverningEntity(reset($items)) : NULL;

      case 'planEntity':
        $payload = "
          logframeEntities (filter: { Id: { eq: {$entity_id} } }) {
            items { " . PlanEntity::GRAPHQL_ITEMS . " }
          }";
        $data = $this->fabricQuery->query($payload);
        $items = $data ? $this->getItems($data, 'logframeEntities') : [];
        return count($items) == 1 ? new PlanEntity(reset($items)) : NULL;
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
    if (count($entity_ids) > 100) {
      // We need to do multiple queries.
      $entities = [];
      for ($i = 0; $i < ceil(count($entity_ids) / 100); $i++) {
        $subset = array_slice($entity_ids, $i * 100, 100);
        $entities = $entities + $this->getEntities($entity_type, $subset);
      }
      return $entities;
    }
    switch ($entity_type) {
      case 'governingEntity':
        $payload = "
          coordinationEntities (filter: { Id: { in: [" . implode(',', $entity_ids) . "] } } ) {
            items { " . GoverningEntity::GRAPHQL_ITEMS . " }
          }";
        $data = $this->fabricQuery->query($payload);
        return $this->buildResultObjectsFromData($data, 'coordinationEntities', GoverningEntity::class);

      case 'planEntity':
        $payload = "
          logframeEntities (filter: { Id: { in: [" . implode(',', $entity_ids) . "] } } ) {
            items { " . PlanEntity::GRAPHQL_ITEMS . " }
          }";
        $data = $this->fabricQuery->query($payload);
        return $this->buildResultObjectsFromData($data, 'logframeEntities', GoverningEntity::class);
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
      'id' => $context_object ? $context_object->id() : NULL,
      'entity_type' => $entity_type,
    ] + ($filters ?? [])));

    $plan_entities = $this->getCache($cache_key);
    if ($plan_entities) {
      return $plan_entities;
    }

    $fetch_coordination_entities = $entity_type === NULL || $entity_type == 'governing';
    $fetch_logframe_entities = $entity_type === NULL || $entity_type == 'plan';

    $payloads = [];
    if ($fetch_coordination_entities) {
      $payloads[] = "
        coordinationEntities (first: 5000, filter: { PlanId: { eq: {$plan_id} } }) {
          items { " . GoverningEntity::GRAPHQL_ITEMS . " }
        }";
    }
    if ($fetch_logframe_entities) {
      $payloads[] = "
        logframeEntities (first: 5000, filter: { PlanId: { eq: {$plan_id} } }) {
          items { " . PlanEntity::GRAPHQL_ITEMS . " }
        }";
    }
    $data = $this->fabricQuery->query(implode(' ', $payloads));
    $coordination_entities = $this->buildResultObjectsFromData($data, 'coordinationEntities', GoverningEntity::class);
    $logframe_entities = $this->buildResultObjectsFromData($data, 'logframeEntities', PlanEntity::class);
    $plan_entities = $coordination_entities + $logframe_entities;

    // Apply filters.
    if (!empty($filters)) {
      $plan_entities = array_filter($plan_entities, fn($entity) => array_intersect_key($entity->toArray(), $filters) == $filters);
    }

    $this->setCache($cache_key, $plan_entities);
    return $plan_entities;
  }

}
