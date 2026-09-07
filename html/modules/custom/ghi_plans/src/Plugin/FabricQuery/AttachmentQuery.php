<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\Facts\FactBase;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQuery as FabricDataQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'attachment' fabric query.
 */
#[FabricQuery(
  id: 'attachment',
  label: new TranslatableMarkup('Attachment query'),
)]
class AttachmentQuery extends FabricQueryBase {

  use AttachmentFilterTrait;
  use PlanQueryTrait;

  /**
   * Get an attachment by its id.
   *
   * @param int $attachment_id
   *   The attachment id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface|null
   *   The attachment object or NULL if not found.
   *
   * @todo Add support for the reporting period.
   */
  public function getAttachment(int $attachment_id): ?AttachmentInterface {
    $attachments = $this->getAttachmentsById([$attachment_id]);
    return !empty($attachments) ? reset($attachments) : NULL;
  }

  /**
   * Get attachments by id.
   *
   * @param array $attachment_ids
   *   The attachment ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The matching (processed) attachment objects, keyed by the attachment id.
   */
  public function getAttachmentsById(array $attachment_ids): array {
    $attachment_ids = array_unique($attachment_ids);
    $attachments = $this->objectStore->getObjects($attachment_ids, Attachment::getObjectStorageKey());
    if (count($attachments) == count($attachment_ids)) {
      return $attachments;
    }
    $attachment_ids = array_diff($attachment_ids, array_keys($attachments));
    sort($attachment_ids);

    if (!empty($attachment_ids)) {
      $items = $this->fabricClient->createQuery('attachments', Attachment::getGraphQlItems())
        ->setFilter('Id', $attachment_ids)
        ->execute() ?: [];
      $new_attachments = $this->processAttachments($items);
      $this->objectStore->addObjects($new_attachments);
      $attachments += $new_attachments;
    }
    return $attachments;
  }

  /**
   * Get attachments by object type and id, optionally filtered.
   *
   * @param array|string $entity_type
   *   The source entity type for an attachment. Use the
   *   PlanEntityInterface::ENTITY_TYPE_* constants.
   * @param array|int $entity_ids
   *   The entity ids that the attachments should belong to.
   * @param array|string $attachment_types
   *   An optional filter for the attachment type.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The matching (processed) attachment objects, keyed by the attachment id.
   */
  public function getAttachmentsByObject(array|string $entity_type, array|int $entity_ids, array|string $attachment_types = []): array {
    $entity_types = (array) $entity_type;
    $entity_ids = array_unique((array) $entity_ids);
    if (empty($entity_ids)) {
      return [];
    }
    $attachment_types = array_map(fn ($item) => ucfirst(strtolower($item)), array_filter((array) $attachment_types));
    sort($entity_ids);

    // Prepare the filters.
    $type_filter_values = array_map(fn ($item) => $this->getEntityTypeFilterValue($item), $entity_types);
    $query_filters = array_filter([
      'EntityMainType' => $type_filter_values,
      'EntityId' => $entity_ids,
      'AttachmentType' => $attachment_types,
    ]);

    // Try to get the requested attachments from the object store.
    $attachments = $this->objectStore->getObjects($entity_ids, Attachment::getObjectStorageKey(), 'EntityId', $query_filters);
    $requested_ids_key = $this->getObjectStoreRequestedIdsKey('EntityId', $query_filters);
    $requested_entity_ids = $this->objectStore->getRequestedIds(Attachment::getObjectStorageKey(), $requested_ids_key);
    $entity_ids = array_diff($entity_ids, $requested_entity_ids);

    if (!empty($entity_ids)) {
      // Do the query.
      $query_filters['EntityId'] = $entity_ids;
      $items = $this->fabricClient->createQuery('attachments', Attachment::getGraphQlItems())
        ->setFilters($query_filters)
        ->execute() ?: [];
      $new_attachments = $this->processAttachments($items);
      $this->objectStore->addObjects($new_attachments);
      $this->objectStore->addRequestedIds(Attachment::getObjectStorageKey(), $entity_ids, $requested_ids_key);
      $attachments += $new_attachments;
    }

    return $attachments;
  }

  /**
   * Build an object-store key for requested entity ids and lookup filters.
   *
   * @param string $property
   *   The lookup property that identifies the requested ids.
   * @param array $filters
   *   The filters applied to the lookup.
   *
   * @return string
   *   A stable requested ids key.
   */
  private function getObjectStoreRequestedIdsKey(string $property, array $filters): string {
    unset($filters[$property]);
    $this->sortObjectStoreRequestedIdsKeyFilters($filters);
    return $property . ':' . hash('sha256', serialize($filters));
  }

  /**
   * Sort requested ids key filters recursively.
   *
   * @param array $filters
   *   The filters to sort.
   */
  private function sortObjectStoreRequestedIdsKeyFilters(array &$filters): void {
    foreach ($filters as &$value) {
      if (!is_array($value)) {
        continue;
      }
      $this->sortObjectStoreRequestedIdsKeyFilters($value);
    }
    if ($filters && array_keys($filters) === range(0, count($filters) - 1)) {
      sort($filters);
      return;
    }
    ksort($filters);
  }

  /**
   * Get all attachments.
   *
   * @param int $plan_id
   *   The plan id.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_object
   *   The current context object.
   * @param array $filter
   *   Optional array for filtering the attachments. This supports specifically
   *   to filter for "entity_type", the allowed values for that are: "plan"
   *   (looking only at plan attachments), "plan_entity" and "governing_entity"
   *   (to look only at attachments on the specific entity type).
   *   Note: Filtering by entity type in this way has a lower priority for the
   *   selection of entities than the passed in context object. So if the
   *   context object is of type "plan_entity" and a $filter['entity_type'] is
   *   set, then it will be ignored.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects for the given context.
   */
  public function getAttachmentsForPlan(int $plan_id, ?ContentEntityInterface $context_object = NULL, array $filter = []): array {
    // Try to get the requested attachments from the object store.
    $attachments = $this->objectStore->getObjectCollection(Attachment::getObjectStorageKey(), 'PlanId', $plan_id);
    if (empty($attachments)) {
      $items = $this->fabricClient->createQuery('attachments', Attachment::getGraphQlItems())
        ->setFilter('PlanId', $plan_id,)
        ->execute() ?: [];
      $attachments = $this->processAttachments($items);
      $this->objectStore->addObjectCollection($attachments, Attachment::getObjectStorageKey(), 'PlanId');
    }

    $filter = array_filter($filter);
    if (!empty($filter)) {
      $this->filterObjects($attachments, $filter);
    }

    // Plan entity attachments need their hierarchy to determine whether they
    // belong to a cluster. Load the plan structure in bulk so the per-object
    // checks below use the shared object store instead of querying Fabric for
    // every attachment source and parent.
    if ($context_object instanceof GoverningEntity) {
      foreach ($attachments as $attachment) {
        // Plan and governing entity attachments can be matched directly from
        // their raw ids, so they do not require hierarchy preloading.
        if ($attachment->getSourceEntityType() !== PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY) {
          continue;
        }
        // One plan-wide preload covers every remaining plan entity attachment;
        // stop scanning once the need for it has been established.
        self::getEntityQuery()?->getEntitiesForPlan($plan_id);
        break;
      }
    }

    if ($context_object instanceof BaseObjectInterface) {
      $attachments = array_filter($attachments, fn (Attachment $attachment) => $attachment->belongsToBaseObject($context_object));
    }
    return $attachments;
  }

  /**
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   The plan entity objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities): array {
    $entities = array_filter($entities, fn($entity): bool => $entity instanceof PlanEntityInterface);
    if (empty($entities)) {
      return [];
    }

    $entity_ids = [];
    foreach ($entities as $entity) {
      $entity_ids[$entity->getEntityType()] = $entity_ids[$entity->getEntityType()] ?? [];
      $entity_ids[$entity->getEntityType()][] = $entity->id();
    }

    $attachment_types = ['Caseload', 'Indicator'];
    $attachments = [];
    foreach (array_keys($entity_ids) as $entity_type) {
      $attachments += $this->getAttachmentsByObject($entity_type, $entity_ids[$entity_type], $attachment_types);
    }
    return $attachments;
  }

  /**
   * Get attachments by plan.
   *
   * @param int[] $plan_ids
   *   The plan ids.
   * @param array|string $attachment_types
   *   Optional array of attachment types for filtering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[][]
   *   An array of array of data attachments, keyed by the plan id and the
   *   attachment id.
   */
  public function getAttachmentsByPlan(array $plan_ids, array|string $attachment_types = []) {
    $attachments = $this->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_PLAN, $plan_ids, $attachment_types);
    $attachments_by_plan = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof Attachment) {
        continue;
      }
      $plan_id = $attachment->getPlanId();
      $attachments_by_plan[$plan_id] = $attachments_by_plan[$plan_id] ?? [];
      $attachments_by_plan[$plan_id][$attachment->id()] = $attachment;
    }
    return $attachments_by_plan;
  }

  /**
   * Get attachments by clusters.
   *
   * @param int[] $cluster_ids
   *   The cluster ids.
   * @param array|string $attachment_types
   *   Optional array of attachment types for filtering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[][]
   *   An array of array of data attachments, keyed by the cluster id and the
   *   attachment id.
   */
  public function getAttachmentsByCluster(array $cluster_ids, array|string $attachment_types = []) {
    $attachments = $this->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY, $cluster_ids, $attachment_types);
    $attachments_by_cluster = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof Attachment || $attachment->getSourceEntityType() != PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY) {
        continue;
      }
      $cluster_id = $attachment->getSourceEntityId();
      $attachments_by_cluster[$cluster_id] = $attachments_by_cluster[$cluster_id] ?? [];
      $attachments_by_cluster[$cluster_id][$attachment->id()] = $attachment;
    }
    return $attachments_by_cluster;
  }

  /**
   * Further process the given list of attachments.
   *
   * @param array $attachments
   *   An array with raw attachment data from fabric.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects, keyed by the attachment id.
   */
  private function processAttachments(array $attachments) {
    return AttachmentHelper::processAttachments($attachments);
  }

  /**
   * Get disaggregated data for an attachment.
   *
   * @param int $attachment_id
   *   The attachment id.
   *
   * @return array
   *   An array of facts representing raw disaggregation data.
   */
  public function getAttachmentDisaggregatedData(int $attachment_id): array {
    return $this->fabricClient->createQuery('attachmentFacts', AttachmentFact::getGraphQlItems())
      ->setFilters([
        'AttachmentId' => $attachment_id,
        'LocationId' => 'NOT NULL',
      ])
      ->execute() ?: [];
  }

  /**
   * Get grouped mappable metric data for an attachment map.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int[] $measurement_ids
   *   Measurement ids to include in the metric-period summary.
   *
   * @return array
   *   Summary data keyed by base/measurements/max/query_succeeded.
   */
  public function getMappableMapMetricSummary(int $attachment_id, array $measurement_ids = []): array {
    $attachment_id = (int) $attachment_id;
    $measurement_ids = array_values(array_unique(array_filter(array_map('intval', $measurement_ids))));
    if ($attachment_id <= 0) {
      return [
        'base' => [],
        'measurements' => [],
        'max' => 0,
        'query_succeeded' => FALSE,
      ];
    }

    $cache_key = $this->getMappableMapMetricSummaryCacheKey($attachment_id, $measurement_ids);
    $summary = &drupal_static(__METHOD__, []);
    if (array_key_exists($cache_key, $summary)) {
      return $summary[$cache_key];
    }

    $result = $this->queryMappableMapMetricSummary($attachment_id, $measurement_ids);
    $summary[$cache_key] = $this->parseMappableMapMetricSummary($result);
    return $summary[$cache_key];
  }

  /**
   * Get mappable metric types for an attachment and its measurements.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment to inspect.
   *
   * @return array|null
   *   Mappable metric types keyed by base and measurement id, or NULL when
   *   availability could not be determined.
   */
  public function getMappableMapMetricAvailability(Attachment $attachment): ?array {
    $measurement_ids = array_values(array_map(fn ($measurement) => (int) $measurement->id(), $attachment->getMeasurements()));
    $summary = $this->getMappableMapMetricSummary($attachment->id(), $measurement_ids);
    if (empty($summary['query_succeeded'])) {
      return NULL;
    }

    $availability = [
      'base' => $this->getMappableMetricTypeNames($attachment, array_keys($summary['base'])),
      'measurements' => [],
    ];
    foreach ($summary['measurements'] as $measurement_id => $metrics) {
      $availability['measurements'][$measurement_id] = $this->getMappableMetricTypeNames($attachment, array_keys($metrics));
    }
    return $availability;
  }

  /**
   * Get map location totals for one metric/period slice.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $metric_type_id
   *   The metric type id.
   * @param int|null $measurement_id
   *   The selected measurement id, if any.
   *
   * @return float[]
   *   Totals keyed by location id.
   */
  public function getAttachmentMapLocationTotals(int $attachment_id, int $metric_type_id, ?int $measurement_id = NULL): array {
    if ($attachment_id <= 0 || $metric_type_id <= 0) {
      return [];
    }
    // Keep map blocks away from Fabric row shape: callers receive a normalized
    // location-id => total map, regardless of whether data came from base or
    // measurement facts.
    $result = $this->queryAttachmentMapLocationTotals($attachment_id, $metric_type_id, $measurement_id);
    return $this->parseAttachmentMapLocationTotals($result);
  }

  /**
   * Get modal map data for one metric/period/location slice.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $metric_type_id
   *   The metric type id.
   * @param int $location_id
   *   The location id.
   * @param int|null $measurement_id
   *   The selected measurement id, if any.
   *
   * @return array
   *   Modal data with keys: total, categories.
   */
  public function getAttachmentMapModalBreakdown(int $attachment_id, int $metric_type_id, int $location_id, ?int $measurement_id = NULL): array {
    // Modal content needs category rows as well as the category-less total
    // rows, so it cannot use the total-only aggregate query used for points.
    $facts = $this->getAttachmentMapFacts($attachment_id, $metric_type_id, $location_id, $measurement_id);
    return $this->buildAttachmentMapModalBreakdown($facts);
  }

  /**
   * Check if there is disaggregated data for an attachment.
   *
   * @param int $attachment_id
   *   The attachment id.
   *
   * @return bool
   *   TRUE if there is disaggregated data for the attachment, FALSE otherwise.
   */
  public function hasDisaggregatedData(int $attachment_id) {
    $availability = $this->hasDisaggregatedDataMultiple([$attachment_id]);
    return !empty($availability[$attachment_id]);
  }

  /**
   * Check if there is disaggregated data for multiple attachments.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   *
   * @return bool[]
   *   The disaggregation availability, keyed by attachment id.
   */
  public function hasDisaggregatedDataMultiple(array $attachment_ids): array {
    $attachment_ids = array_filter(array_map('intval', $attachment_ids), fn($attachment_id) => $attachment_id > 0);
    $attachment_ids = array_values(array_unique($attachment_ids));
    sort($attachment_ids);
    if (empty($attachment_ids)) {
      return [];
    }

    $availability = &drupal_static(__METHOD__, []);
    $missing_attachment_ids = array_values(array_diff($attachment_ids, array_keys($availability)));
    foreach (array_chunk($missing_attachment_ids, FabricDataQuery::MAX_FILTER_COUNT_ARRAY) as $attachment_id_chunk) {
      // Default to FALSE before querying so failed Fabric calls do not keep
      // retrying the same attachments in this request.
      foreach ($attachment_id_chunk as $attachment_id) {
        $availability[$attachment_id] = FALSE;
      }
      $result = $this->queryDisaggregatedDataAvailability($attachment_id_chunk);
      $this->applyDisaggregatedDataAvailabilityResult($availability, $result);
    }

    return array_replace(
      array_fill_keys($attachment_ids, FALSE),
      array_intersect_key($availability, array_flip($attachment_ids))
    );
  }

  /**
   * Check if multiple attachments have data that can render on a map.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   * @param int[][] $measurement_ids_by_attachment_id
   *   Optional measurement ids for the selected reporting period, keyed by
   *   attachment id.
   *
   * @return bool[]
   *   The mappable data availability, keyed by attachment id.
   */
  public function hasMappableDataMultiple(array $attachment_ids, array $measurement_ids_by_attachment_id = []): array {
    $attachment_ids = array_filter(array_map('intval', $attachment_ids), fn($attachment_id) => $attachment_id > 0);
    $attachment_ids = array_values(array_unique($attachment_ids));
    sort($attachment_ids);
    if (empty($attachment_ids)) {
      return [];
    }

    $measurement_ids_by_attachment_id = $this->normalizeMeasurementIdsByAttachmentId($measurement_ids_by_attachment_id, $attachment_ids);
    $availability = &drupal_static(__METHOD__, []);
    $availability_keys = [];
    $missing_attachment_ids = [];
    foreach ($attachment_ids as $attachment_id) {
      $measurement_ids = $measurement_ids_by_attachment_id[$attachment_id] ?? [];
      $availability_key = $this->getMappableDataAvailabilityKey($attachment_id, $measurement_ids);
      $availability_keys[$attachment_id] = $availability_key;
      if (!array_key_exists($availability_key, $availability)) {
        $missing_attachment_ids[] = $attachment_id;
      }
    }

    foreach (array_chunk($missing_attachment_ids, FabricDataQuery::MAX_FILTER_COUNT_ARRAY) as $attachment_id_chunk) {
      foreach ($attachment_id_chunk as $attachment_id) {
        $availability[$availability_keys[$attachment_id]] = FALSE;
      }
      $result = $this->queryMappableDataAvailability(
        $attachment_id_chunk,
        $this->getMeasurementIdsForAttachmentIds($attachment_id_chunk, $measurement_ids_by_attachment_id),
      );
      $this->applyMappableDataAvailabilityResult(
        $availability,
        $result,
        array_intersect_key($availability_keys, array_flip($attachment_id_chunk)),
      );
    }

    $result = [];
    foreach ($attachment_ids as $attachment_id) {
      $result[$attachment_id] = $availability[$availability_keys[$attachment_id]] ?? FALSE;
    }
    return $result;
  }

  /**
   * Query disaggregated data availability for the given attachment ids.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   *
   * @return object|false
   *   A fabric result object, or FALSE if the query failed.
   */
  private function queryDisaggregatedDataAvailability(array $attachment_ids): object|false {
    $filters = [
      'AttachmentId' => $attachment_ids,
      'LocationId' => 'NOT NULL',
    ];
    $queries = array_map(
      fn($query_name) => $this->fabricClient->createQuery($query_name, NULL, $filters)
        ->setAggregation(['AttachmentId'], ['count' => 'Id']),
      ['attachmentFacts', 'measurementFacts'],
    );
    $cache_tags = [];
    foreach ($queries as $query) {
      $cache_tags = Cache::mergeTags($cache_tags, $query->getCacheTags());
    }
    // executeMultiple() flattens aggregations; this caller needs grouped field
    // values from both namespaces to map counts back to attachment ids.
    return $this->fabricClient->query(implode(' ', array_map(fn(FabricDataQuery $query) => $query->toString(), $queries)), cache_tags: $cache_tags);
  }

  /**
   * Query mappable data availability for the given attachments.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   * @param int[] $measurement_ids
   *   Measurement ids for the selected reporting period.
   *
   * @return object|false
   *   A fabric result object, or FALSE if the query failed.
   */
  private function queryMappableDataAvailability(array $attachment_ids, array $measurement_ids = []): object|false {
    // The map JSON drops empty totals and country-level locations, so this
    // availability query mirrors those constraints without hydrating all facts.
    // buildDisaggregatedData() treats facts without category ids as location
    // totals; category split facts only feed the modal category details.
    $filters = [
      'AttachmentId' => $attachment_ids,
      'LocationId' => 'NOT NULL',
      'ValueNum' => ['gt' => 0],
      'location' => [
        'AdminLevel' => ['gt' => 0],
      ],
    ];
    foreach (FactBase::getDisaggregationCategoryFieldNames() as $category_field) {
      $filters[$category_field] = NULL;
    }
    $queries = [
      $this->fabricClient->createQuery('attachmentFacts', NULL, $filters)
        ->setAggregation(['AttachmentId'], ['count' => 'Id']),
    ];
    if (!empty($measurement_ids)) {
      $queries[] = $this->fabricClient->createQuery('measurementFacts', NULL, [
        'AttachmentId' => $attachment_ids,
        'MeasurementId' => $measurement_ids,
      ] + $filters)
        ->setAggregation(['AttachmentId'], ['count' => 'Id']);
    }

    $cache_tags = [];
    foreach ($queries as $query) {
      $cache_tags = Cache::mergeTags($cache_tags, $query->getCacheTags());
    }
    // executeMultiple() flattens aggregations; this caller needs grouped field
    // values from both namespaces to map counts back to attachment ids.
    return $this->fabricClient->query(implode(' ', array_map(fn(FabricDataQuery $query) => $query->toString(), $queries)), cache_tags: $cache_tags);
  }

  /**
   * Query grouped mappable metric summary data.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int[] $measurement_ids
   *   Measurement ids to include in the summary.
   *
   * @return object|false
   *   A fabric result object, or FALSE if the query failed.
   */
  private function queryMappableMapMetricSummary(int $attachment_id, array $measurement_ids = []): object|false {
    $filters = $this->getMappableMapTotalFilters([
      'AttachmentId' => $attachment_id,
    ]);
    // The initial payload only needs to know which metric tabs/variants exist
    // and the global max used for radius scaling. Grouped aggregates are enough
    // for both, and avoid hydrating any per-location fact rows.
    $queries = [
      $this->fabricClient->createQuery('attachmentFacts', NULL, $filters)
        ->setAggregation(['MetricTypeId'], [
          'count' => 'Id',
          'max' => 'ValueNum',
        ]),
    ];
    if (!empty($measurement_ids)) {
      $queries[] = $this->fabricClient->createQuery('measurementFacts', NULL, [
        'MeasurementId' => $measurement_ids,
      ] + $filters)
        ->setAggregation(['MeasurementId', 'MetricTypeId'], [
          'count' => 'Id',
          'max' => 'ValueNum',
        ]);
    }

    $cache_tags = [];
    foreach ($queries as $query) {
      $cache_tags = Cache::mergeTags($cache_tags, $query->getCacheTags());
    }
    return $this->fabricClient->query(implode(' ', array_map(fn(FabricDataQuery $query) => $query->toString(), $queries)), cache_tags: $cache_tags);
  }

  /**
   * Query map location totals for one metric/period slice.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $metric_type_id
   *   The metric type id.
   * @param int|null $measurement_id
   *   The selected measurement id, if any.
   *
   * @return object|false
   *   A fabric result object, or FALSE if the query failed.
   */
  private function queryAttachmentMapLocationTotals(int $attachment_id, int $metric_type_id, ?int $measurement_id = NULL): object|false {
    $filters = $this->getMappableMapTotalFilters([
      'AttachmentId' => $attachment_id,
      'MetricTypeId' => $metric_type_id,
    ]);
    // The map point slice only needs one number per location. Summing in
    // Fabric keeps the JSON small and lets the block skip all category logic.
    $queries = [
      $this->fabricClient->createQuery('attachmentFacts', NULL, $filters)
        ->setAggregation(['LocationId'], [
          'sum' => 'ValueNum',
        ]),
    ];
    if ($measurement_id) {
      $queries[] = $this->fabricClient->createQuery('measurementFacts', NULL, [
        'MeasurementId' => $measurement_id,
      ] + $filters)
        ->setAggregation(['LocationId'], [
          'sum' => 'ValueNum',
        ]);
    }

    $cache_tags = [];
    foreach ($queries as $query) {
      $cache_tags = Cache::mergeTags($cache_tags, $query->getCacheTags());
    }
    return $this->fabricClient->query(implode(' ', array_map(fn(FabricDataQuery $query) => $query->toString(), $queries)), cache_tags: $cache_tags);
  }

  /**
   * Parse grouped mappable metric summary data.
   *
   * @param object|false $result
   *   A fabric result object.
   *
   * @return array
   *   Summary data keyed by base/measurements/max.
   */
  private function parseMappableMapMetricSummary(object|false $result): array {
    $summary = [
      'base' => [],
      'measurements' => [],
      'max' => 0,
      'query_succeeded' => $result !== FALSE,
    ];
    if (!$result) {
      return $summary;
    }

    foreach ($result?->attachmentFacts?->groupBy ?? [] as $group) {
      $metric_type_id = (int) ($group?->fields?->MetricTypeId ?? 0);
      $count = (int) ($group?->aggregations?->count ?? 0);
      if ($metric_type_id <= 0 || $count <= 0) {
        continue;
      }
      $max = (float) ($group?->aggregations?->max ?? 0);
      $summary['base'][$metric_type_id] = [
        'count' => $count,
        'max' => $max,
      ];
      $summary['max'] = max($summary['max'], $max);
    }

    foreach ($result?->measurementFacts?->groupBy ?? [] as $group) {
      $measurement_id = (int) ($group?->fields?->MeasurementId ?? 0);
      $metric_type_id = (int) ($group?->fields?->MetricTypeId ?? 0);
      $count = (int) ($group?->aggregations?->count ?? 0);
      if ($measurement_id <= 0 || $metric_type_id <= 0 || $count <= 0) {
        continue;
      }
      $max = (float) ($group?->aggregations?->max ?? 0);
      $summary['measurements'][$measurement_id][$metric_type_id] = [
        'count' => $count,
        'max' => $max,
      ];
      $summary['max'] = max($summary['max'], $max);
    }

    return $summary;
  }

  /**
   * Convert mappable metric type ids to attachment field names.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment whose fields are being inspected.
   * @param int[] $metric_type_ids
   *   The metric type ids.
   *
   * @return string[]
   *   The corresponding metric type machine names.
   */
  private function getMappableMetricTypeNames(Attachment $attachment, array $metric_type_ids): array {
    $metric_types = [];
    foreach ($metric_type_ids as $metric_type_id) {
      $metric_type = $this->getMetricType((int) $metric_type_id);
      $machine_name = $metric_type?->getMachineName();
      if (!$machine_name) {
        continue;
      }
      if ($machine_name == 'custom') {
        foreach (array_keys($attachment->getFields()) as $field_name) {
          if ($field_name == 'custom' || str_starts_with($field_name, 'custom__')) {
            $metric_types[] = $field_name;
          }
        }
        continue;
      }
      $metric_types[] = $machine_name;
    }
    return array_values(array_unique($metric_types));
  }

  /**
   * Parse map location total groups.
   *
   * @param object|false $result
   *   A fabric result object.
   *
   * @return float[]
   *   Totals keyed by location id.
   */
  private function parseAttachmentMapLocationTotals(object|false $result): array {
    $totals = [];
    if (!$result) {
      return $totals;
    }

    foreach (['attachmentFacts', 'measurementFacts'] as $namespace) {
      foreach ($result?->{$namespace}?->groupBy ?? [] as $group) {
        $location_id = (int) ($group?->fields?->LocationId ?? 0);
        $total = (float) ($group?->aggregations?->sum ?? 0);
        if ($location_id <= 0 || $total <= 0) {
          continue;
        }
        // Base and measurement namespaces can both contribute to the same
        // metric slice; normalize them into one location total.
        $totals[$location_id] = ($totals[$location_id] ?? 0) + $total;
      }
    }
    return $totals;
  }

  /**
   * Build modal breakdown data from fact rows.
   *
   * @param array $facts
   *   Raw fact rows keyed by namespace.
   *
   * @return array
   *   Modal data with keys: total, categories.
   */
  private function buildAttachmentMapModalBreakdown(array $facts): array {
    $breakdown = [
      'total' => 0,
      'categories' => [],
    ];

    foreach ($this->buildAttachmentMapFactObjects($facts) as $fact) {
      // Category-less rows are the location totals. Rows with any category id
      // feed the modal breakdown table instead.
      if (!$fact->hasDisaggregationCategories()) {
        $breakdown['total'] += $fact->getValue();
        continue;
      }

      $category_label = $fact->getCombinedCategoryLabel();
      if (!$category_label) {
        continue;
      }
      $breakdown['categories'][$category_label] = [
        'name' => $category_label,
        'value' => ($breakdown['categories'][$category_label]['value'] ?? 0) + $fact->getValue(),
      ];
    }

    ksort($breakdown['categories']);
    $breakdown['categories'] = array_map(fn (array $category) => (object) $category, $breakdown['categories']);
    return $breakdown;
  }

  /**
   * Build fact objects for a small modal fact set.
   *
   * @param array $facts
   *   Raw fact rows keyed by namespace.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\FactBase[]
   *   Fact objects.
   */
  private function buildAttachmentMapFactObjects(array $facts): array {
    $objects = [];
    foreach ($facts['attachmentFacts'] ?? [] as $row) {
      $objects[] = new AttachmentFact($row);
    }
    foreach ($facts['measurementFacts'] ?? [] as $row) {
      $objects[] = new MeasurementFact($row);
    }
    return $objects;
  }

  /**
   * Get modal fact rows for one metric/period/location slice.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $metric_type_id
   *   The metric type id.
   * @param int $location_id
   *   The location id.
   * @param int|null $measurement_id
   *   The selected measurement id, if any.
   *
   * @return array
   *   Raw fact rows keyed by namespace.
   */
  private function getAttachmentMapFacts(int $attachment_id, int $metric_type_id, int $location_id, ?int $measurement_id = NULL): array {
    if ($attachment_id <= 0 || $metric_type_id <= 0 || $location_id <= 0) {
      return [];
    }

    $filters = [
      'AttachmentId' => $attachment_id,
      'MetricTypeId' => $metric_type_id,
      'LocationId' => $location_id,
      'location' => [
        'AdminLevel' => ['gt' => 0],
      ],
    ];

    // Deliberately do not call getMappableMapTotalFilters(): modals need both
    // category-less total rows and category split rows for the selected point.
    $queries = [
      $this->fabricClient->createQuery('attachmentFacts', $this->getMapFactItems(FALSE), $filters),
    ];
    if ($measurement_id) {
      $queries[] = $this->fabricClient->createQuery('measurementFacts', $this->getMapFactItems(), [
        'MeasurementId' => $measurement_id,
      ] + $filters);
    }

    $result = $this->fabricClient->executeMultiple($queries);
    return is_array($result) ? array_filter($result) : [];
  }

  /**
   * Add the common mappable total-row filters.
   *
   * @param array $filters
   *   Existing Fabric filters.
   *
   * @return array
   *   Filters restricted to map-visible total rows.
   */
  private function getMappableMapTotalFilters(array $filters): array {
    $filters += [
      'LocationId' => 'NOT NULL',
      'ValueNum' => ['gt' => 0],
      'location' => [
        'AdminLevel' => ['gt' => 0],
      ],
    ];
    // Do not rely on IsTotal here. The established disaggregated-data builder
    // treats rows without category ids as location totals, and the map logic
    // mirrors that behavior so availability, totals, and modal details agree.
    foreach (FactBase::getDisaggregationCategoryFieldNames() as $category_field) {
      $filters[$category_field] = NULL;
    }
    return $filters;
  }

  /**
   * Get minimal fact fields needed to build attachment map modal data.
   *
   * @param bool $include_measurement_id
   *   Whether to include the MeasurementId field.
   *
   * @return string[]
   *   The fact fields.
   */
  private function getMapFactItems(bool $include_measurement_id = TRUE): array {
    // Modal fact objects only need enough fields to identify totals,
    // disaggregation categories, labels, and numeric values.
    $items = array_merge([
      'Id',
      'AttachmentId',
      'MeasurementId',
      'MetricTypeId',
      'CustomMetricName',
      'LocationId',
    ], FactBase::getDisaggregationCategoryFieldNames(), [
      'IsTotal',
      'ValueNum',
    ]);
    if (!$include_measurement_id) {
      $items = array_diff($items, ['MeasurementId']);
    }
    return array_values(array_unique($items));
  }

  /**
   * Build a cache key for metric summary data.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int[] $measurement_ids
   *   Measurement ids.
   *
   * @return string
   *   A cache key.
   */
  private function getMappableMapMetricSummaryCacheKey(int $attachment_id, array $measurement_ids): string {
    sort($measurement_ids);
    return $attachment_id . ':' . implode(',', $measurement_ids);
  }

  /**
   * Apply a disaggregation availability result to the given availability map.
   *
   * @param bool[] $availability
   *   The availability map, keyed by attachment id.
   * @param object|false $result
   *   The fabric result object.
   */
  private function applyDisaggregatedDataAvailabilityResult(array &$availability, object|false $result): void {
    if (!$result) {
      return;
    }

    foreach (['attachmentFacts', 'measurementFacts'] as $namespace) {
      $groups = $result?->{$namespace}?->groupBy ?? [];
      foreach ($groups as $group) {
        $attachment_id = $group?->fields?->AttachmentId ?? NULL;
        $count = $group?->aggregations?->count ?? 0;
        if (!$attachment_id || $count <= 0) {
          continue;
        }
        $availability[(int) $attachment_id] = TRUE;
      }
    }
  }

  /**
   * Apply a mappable data availability result to the given availability map.
   *
   * @param bool[] $availability
   *   The availability map, keyed by availability key.
   * @param object|false $result
   *   The fabric result object.
   * @param string[] $availability_keys_by_attachment_id
   *   Availability keys keyed by attachment id.
   */
  private function applyMappableDataAvailabilityResult(array &$availability, object|false $result, array $availability_keys_by_attachment_id): void {
    if (!$result) {
      return;
    }

    foreach (['attachmentFacts', 'measurementFacts'] as $namespace) {
      $groups = $result?->{$namespace}?->groupBy ?? [];
      foreach ($groups as $group) {
        $attachment_id = $group?->fields?->AttachmentId ?? NULL;
        $count = $group?->aggregations?->count ?? 0;
        if (!$attachment_id || $count <= 0 || empty($availability_keys_by_attachment_id[$attachment_id])) {
          continue;
        }
        $availability[$availability_keys_by_attachment_id[$attachment_id]] = TRUE;
      }
    }
  }

  /**
   * Normalize selected measurement ids by attachment id.
   *
   * @param int[][] $measurement_ids_by_attachment_id
   *   Measurement ids keyed by attachment id.
   * @param int[] $attachment_ids
   *   The attachment ids to preserve.
   *
   * @return int[][]
   *   The normalized measurement ids keyed by attachment id.
   */
  private function normalizeMeasurementIdsByAttachmentId(array $measurement_ids_by_attachment_id, array $attachment_ids): array {
    $normalized = [];
    foreach ($attachment_ids as $attachment_id) {
      $measurement_ids = $measurement_ids_by_attachment_id[$attachment_id] ?? [];
      $measurement_ids = array_filter(array_map('intval', (array) $measurement_ids), fn($measurement_id) => $measurement_id > 0);
      $measurement_ids = array_values(array_unique($measurement_ids));
      sort($measurement_ids);
      if (!empty($measurement_ids)) {
        $normalized[$attachment_id] = $measurement_ids;
      }
    }
    return $normalized;
  }

  /**
   * Get measurement ids for the given attachments.
   *
   * @param int[] $attachment_ids
   *   The attachment ids.
   * @param int[][] $measurement_ids_by_attachment_id
   *   Measurement ids keyed by attachment id.
   *
   * @return int[]
   *   The measurement ids.
   */
  private function getMeasurementIdsForAttachmentIds(array $attachment_ids, array $measurement_ids_by_attachment_id): array {
    $measurement_ids = [];
    foreach ($attachment_ids as $attachment_id) {
      $measurement_ids = array_merge($measurement_ids, $measurement_ids_by_attachment_id[$attachment_id] ?? []);
    }
    $measurement_ids = array_values(array_unique($measurement_ids));
    sort($measurement_ids);
    return $measurement_ids;
  }

  /**
   * Build an availability cache key for an attachment/reporting-period pair.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int[] $measurement_ids
   *   Measurement ids used for this availability check.
   *
   * @return string
   *   The availability cache key.
   */
  private function getMappableDataAvailabilityKey(int $attachment_id, array $measurement_ids): string {
    sort($measurement_ids);
    return $attachment_id . ':' . implode(',', $measurement_ids);
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
  private function getEntityTypeFilterValue($entity_type): ?string {
    return match ($entity_type) {
      PlanEntityInterface::ENTITY_TYPE_PLAN => 'Plan',
      PlanEntityInterface::ENTITY_TYPE_PLAN_ENTITY => 'LogframeEntity',
      PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY => 'CoordinationEntity',
      'plan_entity' => 'LogframeEntity',
      'governing_entity' => 'CoordinationEntity',
      default => NULL,
    };
  }

}
