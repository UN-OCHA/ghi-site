<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
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
    $attachment = $this->objectStore->getObject($attachment_id, DataAttachment::getObjectStorageKey());
    if ($attachment) {
      return $attachment;
    }

    $queries = [
      $this->fabricClient->createQuery('attachments', DataAttachment::getGraphQlItems())
        ->setFilter('Id', $attachment_id),
      $this->fabricClient->createQuery('attachmentFacts', AttachmentFact::getGraphQlItems())
        ->setFilters([
          'AttachmentId' => $attachment_id,
          'IsTotal' => TRUE,
        ]),
      $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
        ->setFilters([
          'AttachmentId' => $attachment_id,
        ]),
    ];

    $data = $this->fabricClient->executeMultiple($queries);
    $attachments = $data['attachments'];

    if (empty($attachments)) {
      return NULL;
    }

    // Retrieving an attachment by id should yield exactly one.
    assert(count($attachments) <= 1);

    $attachments = $this->processAttachments($attachments, $data);
    $attachment = reset($attachments);
    $this->objectStore->addObject($attachment);
    return $attachment;
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
    if (count($attachment_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      return $this->doChunkedQuery($attachment_ids, fn ($ids): array => $this->getAttachmentsById($ids));
    }
    $attachments = $this->objectStore->getObjects($attachment_ids, DataAttachment::getObjectStorageKey());
    if (count($attachments) == count($attachment_ids)) {
      return $attachments;
    }
    $attachment_ids = array_diff($attachment_ids, array_keys($attachments));
    sort($attachment_ids);

    $queries = [
      $this->fabricClient->createQuery('attachments', DataAttachment::getGraphQlItems())
        ->setFilter('Id', $attachment_ids),
      $this->fabricClient->createQuery('attachmentFacts', AttachmentFact::getGraphQlItems())
        ->setFilters([
          'AttachmentId' => $attachment_ids,
          'IsTotal' => TRUE,
        ]),
      $this->fabricClient->createQuery('measurements', Measurement::getGraphQlItems())
        ->setFilters([
          'AttachmentId' => $attachment_ids,
        ]),
    ];
    $data = $this->fabricClient->executeMultiple($queries);
    $attachments = $this->processAttachments($data['attachments'] ?? [], $data);
    $this->objectStore->addObjects($attachments);
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
    ]);

    // Try to get the requested attachments from the object store.
    $attachments = $this->objectStore->getObjects($entity_ids, DataAttachment::getObjectStorageKey(), 'EntityId', $query_filters);

    $requested_ids = $this->objectStore->getRequestedIds(DataAttachment::getObjectStorageKey(), __FUNCTION__);
    $entity_ids = array_diff($entity_ids, $requested_ids);
    if (!empty($entity_ids)) {

      // Keep track of what we have already requested.
      $this->objectStore->addRequestedIds(DataAttachment::getObjectStorageKey(), $entity_ids, __FUNCTION__);

      // Do the query.
      $query_filters['EntityId'] = $entity_ids;
      $items = $this->fabricClient->createQuery('attachments', DataAttachment::getGraphQlItems())
        ->setFilters($query_filters)
        ->execute();
      if (!empty($items)) {
        // Process the results.
        $attachments += $this->processAttachments($items);
      }
    }

    // Store the results.
    $this->objectStore->addObjects($attachments);
    if (!empty($attachment_types)) {
      $this->filterObjects($attachments, ['AttachmentType' => $attachment_types]);
    }
    return $attachments;
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of attachment objects for the given context.
   */
  public function getAttachmentsForPlan(int $plan_id, ?ContentEntityInterface $context_object = NULL, array $filter = []): array {
    $type_filter_value = NULL;
    $supported_contexts = ['plan_entity', 'governing_entity'];
    if ($context_object && $entity_type = ($supported_contexts[$context_object->bundle()] ?? NULL)) {
      $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
    }
    $query_filters = array_filter([
      'PlanId' => $plan_id,
      'EntityMainType' => $type_filter_value,
    ]);

    // Try to get the requested attachments from the object store.
    $attachments = $this->objectStore->getObjectCollection(DataAttachment::getObjectStorageKey(), 'attachments_by_plan', $plan_id);
    if (empty($attachments)) {
      $attachments = $this->fabricClient->createQuery('attachments', DataAttachment::getGraphQlItems())
        ->setFilters($query_filters)
        ->execute();
    }

    if (!is_array($attachments) || empty($attachments)) {
      return [];
    }

    if (!empty($filter)) {
      $attachments = $this->filterObjects($attachments, $filter);
    }

    $attachments = $this->processAttachments($attachments);
    $this->objectStore->addObjectCollection($attachments, DataAttachment::getObjectStorageKey(), 'attachments_by_plan');
    return $attachments;
  }

  /**
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   The plan entity objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities): array {
    $entities = array_filter($entities, fn(PlanEntityInterface $entity): bool => $entity instanceof PlanEntityInterface);
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[][]
   *   An array of array of data attachments, keyed by the plan id and the
   *   attachment id.
   */
  public function getAttachmentsByPlan(array $plan_ids, array|string $attachment_types = []) {
    $attachments = $this->getAttachmentsByObject('plan', $plan_ids, $attachment_types);
    $attachments_by_plan = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof DataAttachment) {
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[][]
   *   An array of array of data attachments, keyed by the cluster id and the
   *   attachment id.
   */
  public function getAttachmentsByCluster(array $cluster_ids, array|string $attachment_types = []) {
    $attachments = $this->getAttachmentsByObject('governingEntity', $cluster_ids, $attachment_types);
    $attachments_by_cluster = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof DataAttachment || $attachment->getSourceEntityType() != 'governingEntity') {
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
   * @param array|null $data
   *   An optional array of query result objects, keyed by the query name. If
   *   NULL, the necessary data will be retrieved from fabric using the
   *   attachment ids as condition.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   An array of attachment objects, keyed by the attachment id.
   */
  private function processAttachments(array $attachments, ?array $data = NULL) {
    $attachment_ids = $this->extractIdsFromRawData($attachments);
    $attachments = array_combine($attachment_ids, $attachments);

    // If we have found attachments, also load the total facts.
    $this->addAttachmentFacts($attachments, $data['attachmentFacts'] ?? NULL);

    // If we have found attachments, also load the measurement totals.
    $this->addMeasurements($attachments, $data['measurements'] ?? NULL);

    return AttachmentHelper::processAttachments($attachments);
  }

  /**
   * Add attachment facts to the given set of attachments.
   *
   * @param array $attachments
   *   An array with raw attachment data from fabric.
   * @param array|null $attachment_facts
   *   An optional set of query result objects. If NULL, the facts will be
   *   retrieved from fabric using the attachment ids as condition.
   */
  private function addAttachmentFacts(&$attachments, ?array $attachment_facts = NULL) {
    if (!$attachment_facts) {
      $attachment_facts = $this->fabricClient->createQuery('attachmentFacts', AttachmentFact::getGraphQlItems())
        ->setFilters([
          'AttachmentId' => array_keys($attachments),
          'IsTotal' => TRUE,
        ])
        ->execute();
    }
    foreach ($attachment_facts as $attachment_fact) {
      $attachment_id = $attachment_fact->AttachmentId;
      $attachments[$attachment_id]->totals = $attachments[$attachment_id]->totals ?? [];
      $attachments[$attachment_id]->totals[$attachment_fact->Id] = $attachment_fact;
    }
  }

  /**
   * Add measurements to the given set of attachments.
   *
   * @param array $attachments
   *   An array with raw attachment data from fabric.
   * @param array|null $measurements
   *   An optional set of query result objects. If NULL, the measurements will
   *   be retrieved from fabric using the attachment ids as condition.
   */
  private function addMeasurements(&$attachments, ?array $measurements = NULL) {
    if (!$measurements) {
      $attachment_ids = $this->extractIdsFromRawData($attachments);
      $measurements = $this->getMeasurementQuery()->getMeasurementsByAttachmentId($attachment_ids);
    }
    else {
      $measurement_ids = $this->extractIdsFromRawData($measurements);
      $measurements = $this->getMeasurementQuery()->getMeasurementsById($measurement_ids);
    }

    /** @var \Drupal\ghi_plans\ApiObjects\Measurements\MeasurementInterface[] $measurements */
    if (empty($measurements)) {
      return;
    }

    $measurement_ids = $this->extractIds($measurements);
    $measurement_facts = $this->getMeasurementQuery()->getMeasurementFactsByMeasurementId($measurement_ids);

    if (empty($measurement_facts)) {
      return;
    }

    $plan_query = $this->getPlanQuery();
    $plan_ids = array_unique(array_map(fn ($item) => $item->PlanId, $attachments));
    $plans = $plan_query->getPlansById($plan_ids);
    $current_monitoring_period_ids = array_filter(array_map(fn (Plan $item): ?int => $item->getLastPublishedReportingPeriodId(), $plans));

    foreach ($measurements as $measurement) {
      $attachment_id = $measurement->getAttachmentId();
      $attachments[$attachment_id]->measurements = $attachments[$attachment_id]->measurements ?? [];
      $attachments[$attachment_id]->measurements[$measurement->id()] = $measurement->getRawData();
    }

    foreach ($measurement_facts as $measurement_fact) {
      $fact_id = $measurement_fact->Id;
      $measurement_id = $measurement_fact->MeasurementId;
      $measurement = $measurements[$measurement_id];
      $period_id = $measurement->getReportingPeriodId();
      $attachment_id = $measurement->getAttachmentId();

      $attachments[$attachment_id]->measurements[$measurement_id]->totals = $attachments[$attachment_id]->measurements[$measurement_id]->totals ?? [];
      $attachments[$attachment_id]->measurements[$measurement_id]->totals[$fact_id] = $measurement_fact;

      if (in_array($period_id, $current_monitoring_period_ids)) {
        // Merge the measurement facts of the current monitoring period into
        // the attachment totals.
        $attachments[$attachment_id]->totals = $attachments[$attachment_id]->totals ?? [];
        $attachments[$attachment_id]->totals[$fact_id] = $measurement_fact;
      }
    }
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
    // Get the attachment facts.
    return $this->fabricClient->createQuery('attachmentFacts', AttachmentFact::getGraphQlItems())
      ->setFilters([
        'AttachmentId' => $attachment_id,
        'IsTotal' => FALSE,
      ])
      ->execute() ?: [];
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
