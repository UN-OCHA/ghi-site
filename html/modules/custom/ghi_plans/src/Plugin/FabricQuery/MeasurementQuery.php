<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'measurement' fabric query.
 */
#[FabricQuery(
  id: 'measurement',
  label: new TranslatableMarkup('Measurement query'),
)]
class MeasurementQuery extends FabricQueryBase {

  use AttachmentFilterTrait;

  /**
   * Get an measurement by its id.
   *
   * @param int $measurement_id
   *   The measurement id.
   * @param int $reporting_period
   *   The reporting period for which to load the measurement data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface|null
   *   The measurement object or NULL if not found.
   *
   * @todo Add support for the reporting period.
   */
  public function getMeasurement(int $measurement_id, ?int $reporting_period = NULL): ?MeasurementInterface {
    $measurements = $this->getMeasurementsById([$measurement_id]);
    return !empty($measurements) ? reset($measurements) : NULL;
  }

  /**
   * Get measurements by id.
   *
   * @param array $measurement_ids
   *   The measurement ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[]
   *   An array of measurement objects, keyed by the measurement id.
   */
  public function getMeasurementsById(array $measurement_ids): array {
    if (count($measurement_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      return $this->doChunkedQuery($measurement_ids, fn ($ids): array => $this->getMeasurementsById($ids));
    }
    $measurements = $this->objectStore->getObjects($measurement_ids, Measurement::getObjectStorageKey());
    if (count($measurements) == count($measurement_ids)) {
      return $measurements;
    }
    $measurement_ids = array_diff($measurement_ids, array_keys($measurements));
    sort($measurement_ids);

    if (!empty($measurement_ids)) {
      $items = $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
        ->setFilter('Id', $measurement_ids)
        ->execute() ?: [];
      $new_measurements = $this->buildResultObjects($items, Measurement::class);
      $this->objectStore->addObjects($new_measurements);
      $measurements += $new_measurements;
    }
    return $measurements;
  }

  /**
   * Get measurements by attachment id.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[]
   *   An array of measurement objects, keyed by the measurement id.
   */
  public function getMeasurementsByAttachmentId(array $attachment_ids): array {
    if (count($attachment_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      return $this->doChunkedQuery($attachment_ids, fn ($ids): array => $this->getMeasurementsByAttachmentId($ids));
    }
    $measurements = $this->objectStore->getObjects($attachment_ids, Measurement::getObjectStorageKey(), 'AttachmentId');
    if (count($measurements) == count($attachment_ids)) {
      return $measurements;
    }
    $attachment_ids = array_diff($attachment_ids, array_keys($measurements));
    sort($attachment_ids);
    if (!empty($attachment_ids)) {
      $items = $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
        ->setFilter('AttachmentId', $attachment_ids)
        ->execute() ?: [];
      $new_measurements = $this->buildResultObjects($items, Measurement::class);
      $this->objectStore->addObjects($new_measurements);
      $measurements += $new_measurements;
    }
    return $measurements;
  }

  /**
   * Get measurement by object type and id, optionally filtered.
   *
   * @param string $entity_type
   *   The entity type for an measurement, either "governingEntity" or
   *   "planEntity".
   * @param array|int $entity_ids
   *   The entity ids that the measurement should belong to.
   * @param array|string $measurement_types
   *   An optional filter for the measurement type.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[]
   *   An array of measurement objects, keyed by the measurement id.
   */
  public function getMeasurementsByObject(string $entity_type, array|int $entity_ids, array|string $measurement_types = []): array {
    $entity_ids = (array) $entity_ids;
    $measurement_types = array_filter((array) $measurement_types);
    sort($entity_ids);

    $cache_key = $this->getCacheKey([
      'entity_type' => $entity_type,
      'entity_ids' => $entity_ids,
    ] + $measurement_types);
    $measurements = $this->getCache($cache_key);
    if ($measurements) {
      return $measurements;
    }

    $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
    $filters = array_filter([
      'EntityMainType' => $type_filter_value,
      'EntityId' => $entity_ids,
      'MeasurementType' => $measurement_types ?: NULL,
    ]);

    $items = $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
      ->setFilters($filters)
      ->execute() ?: [];
    $measurements = $this->buildResultObjects($items, Measurement::class);

    $this->setCache($cache_key, $measurements);
    return $measurements;
  }

  /**
   * Get all measurements.
   *
   * @param int $plan_id
   *   The plan id.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param array $filter
   *   Optional array for filtering the measurements. This supports specifically
   *   to filter for "entity_type", the allowed values for that are: "plan"
   *   (looking only at plan measurements), "plan_entity" and "governing_entity"
   *   (to look only at measurements on the specific entity type).
   *   Note: Filtering by entity type in this way has a lower priority for the
   *   selection of entities than the passed in context object. So if the
   *   context object is of type "plan_entity" and a $filter['entity_type'] is
   *   set, then it will be ignored.
   * @param bool $fetch_facts
   *   Whether to fetch the measurement facts too.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[]
   *   An array of measurement objects for the given context.
   */
  public function getMeasurementsForPlan(int $plan_id, ?ContentEntityInterface $context_object = NULL, array $filter = [], bool $fetch_facts = FALSE): array {
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $plan_id,
      'context_type' => $context_object?->bundle() ?? NULL,
      'context_id' => $context_object?->id() ?? NULL,
      'fetch_facts' => (int) $fetch_facts,
    ] + $filter));
    $measurements = $this->getCache($cache_key);
    if ($measurements) {
      return $measurements;
    }

    // Supported types of context objects.
    $supported_contexts = [
      'plan_entity',
      'governing_entity',
    ];

    $type_filter_value = NULL;
    if ($context_object && $entity_type = $supported_contexts[$context_object->bundle()] ?? NULL) {
      $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
    }

    $measurement_types = !empty($filter['MeasurementType']) ? (array) $filter['MeasurementType'] : NULL;
    unset($filter['MeasurementType']);

    $items = $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
      ->setFilters(array_filter([
        'PlanId' => $plan_id,
        'EntityMainType' => $type_filter_value,
        'MeasurementType' => $measurement_types,
      ]))
      ->execute() ?: [];
    $measurements = $this->buildResultObjects($items, Measurement::class);

    // phpcs:disable
    // if (!empty($filter)) {
    //   $measurements = $this->filterAttachments($measurements, $filter);
    // }
    // phpcs:enable

    $this->setCache($cache_key, $measurements);
    return $measurements;
  }

  /**
   * Get measurements by plan.
   *
   * @param int[] $plan_ids
   *   The plan ids.
   * @param array|string $measurement_types
   *   Optional array of measurement types for filtering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[][]
   *   An array of array of data measurements, keyed by the plan id and the
   *   measurement id.
   */
  public function getMeasurementsByPlan(array $plan_ids, array|string $measurement_types = []): array {
    $measurements = $this->getMeasurementsByObject('plan', $plan_ids, $measurement_types);
    $measurements_by_plan = [];
    foreach ($measurements as $measurement) {
      $plan_id = $measurement->getPlanId();
      $measurements_by_plan[$plan_id] = $measurements_by_plan[$plan_id] ?? [];
      $measurements_by_plan[$plan_id][$measurement->id()] = $measurement;
    }
    return $measurements_by_plan;
  }

  /**
   * Get measurements by clusters.
   *
   * @param int[] $cluster_ids
   *   The cluster ids.
   * @param array|string $measurement_types
   *   Optional array of measurement types for filtering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[][]
   *   An array of array of data measurements, keyed by the cluster id and the
   *   measurement id.
   */
  public function getMeasurementsByCluster(array $cluster_ids, array|string $measurement_types = []): array {
    $measurements = $this->getMeasurementsByObject('governingEntity', $cluster_ids, $measurement_types);
    $measurements_by_cluster = [];
    foreach ($measurements as $measurement) {
      if ($measurement->getSourceEntityType() != 'governingEntity') {
        continue;
      }
      $cluster_id = $measurement->getSourceEntityId();
      $measurements_by_cluster[$cluster_id] = $measurements_by_cluster[$cluster_id] ?? [];
      $measurements_by_cluster[$cluster_id][$measurement->id()] = $measurement;
    }
    return $measurements_by_cluster;
  }

  /**
   * Get disaggregated data for an measurement.
   *
   * @param int $measurement_id
   *   The measurement id.
   *
   * @return array
   *   An array of facts representing raw disaggregation data.
   */
  public function getMeasurementDisaggregatedData(int $measurement_id): array {
    $measurement_facts = $this->fabricClient->createQuery('measurementFacts', MeasurementFact::getGraphQlItems())
      ->setFilters([
        'MeasurementId' => $measurement_id,
        'LocationId' => 'NOT NULL',
      ])
      ->execute();
    return $measurement_facts ?: [];
  }

  /**
   * Get the filter value for an entity type.
   *
   * @param string $entity_type
   *   The entity type used internally in HA.
   *
   * @return string|null
   *   The filter value for that entity type or NULL.
   */
  private function getEntityTypeFilterValue(string $entity_type): ?string {
    return match ($entity_type) {
      'plan' => 'Plan',
      'planEntity' => 'LogframeEntity',
      'plan_entity' => 'LogframeEntity',
      'governingEntity' => 'CoordinationEntity',
      'governing_entity' => 'CoordinationEntity',
      default => NULL,
    };
  }

}
