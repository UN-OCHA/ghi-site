<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
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
   *   The entity type for an attachment, either "governingEntity" or
   *   "planEntity".
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

    $type_filter_value = NULL;
    $supported_contexts = ['plan_entity', 'governing_entity'];
    if ($context_object && $entity_type = ($supported_contexts[$context_object->bundle()] ?? NULL)) {
      $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
    }
    $filter = array_filter($filter + [
      'EntityMainType' => $type_filter_value,
    ]);

    if (!empty($filter)) {
      $this->filterObjects($attachments, $filter);
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
    $attachments = $this->getAttachmentsByObject('plan', $plan_ids, $attachment_types);
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
    $attachments = $this->getAttachmentsByObject('governingEntity', $cluster_ids, $attachment_types);
    $attachments_by_cluster = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof Attachment || $attachment->getSourceEntityType() != 'governingEntity') {
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
      'plan' => 'Plan',
      'planEntity' => 'LogframeEntity',
      'plan_entity' => 'LogframeEntity',
      'governingEntity' => 'CoordinationEntity',
      'governing_entity' => 'CoordinationEntity',
      default => NULL,
    };
  }

}
