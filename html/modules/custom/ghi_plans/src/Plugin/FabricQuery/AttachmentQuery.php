<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
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

  /**
   * Get an attachment by its id.
   *
   * @param int $attachment_id
   *   The attachment id.
   * @param int $reporting_period
   *   The reporting period for which to load the attachment data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface|null
   *   The attachment object or NULL if not found.
   *
   * @todo Add support for the reporting period.
   */
  public function getAttachment(int $attachment_id, $reporting_period = NULL): ?AttachmentInterface {
    $attachment = NULL;
    $payload = "
      attachments (filter: {
        Id:  {
          eq: {$attachment_id}
        }
      }) {
        items {" . DataAttachment::GRAPHQL_DIMENSION_ITEMS . "}
      }
      attachmentFacts (filter: {
        IsTotal: { eq: true }
        AttachmentId:  {
          eq: {$attachment_id}
        }
      }) {
        items {" . AttachmentFact::GRAPHQL_FACTS_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    $attachments = $this->getItems($data, 'attachments');
    // Retrieving an attachment by id should yield a max of 1, so let's assert
    // that.
    assert(count($attachments) <= 1);

    $attachment = reset($attachments);
    if (empty($attachment)) {
      return NULL;
    }

    $attachment->totals = $this->getItems($data, 'attachmentFacts') ?: [];

    return AttachmentHelper::processAttachment($attachment);
  }

  /**
   * Get attachments by id.
   *
   * @param array $attachment_ids
   *   The attachment ids.
   * @param bool $disaggregated
   *   Whether to fecth disaggregated data or not.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The matching (processed) attachment objects, keyed by the attachment id.
   */
  public function getAttachmentsById(array $attachment_ids, $disaggregated = FALSE) {
    sort($attachment_ids);

    $cache_key = $this->getCacheKey([
      'attachment_ids' => $attachment_ids,
      'disaggregated' => (int) $disaggregated,
    ]);
    $attachments = $this->getCache($cache_key);
    if ($attachments) {
      return $attachments;
    }
    $payload = "
      attachments (first: 10000, filter: {
        Id:  {
          in: [" . implode(',', $attachment_ids) . "]
        }
      }) {
        items {" . DataAttachment::GRAPHQL_DIMENSION_ITEMS . "}
      }
      attachmentFacts (first: 100000, filter: {
        IsTotal: { eq: " . ($disaggregated ? 'false' : 'true') . " }
        AttachmentId:  {
          in: [" . implode(',', $attachment_ids) . "]
        }
      }) {
        items {" . AttachmentFact::GRAPHQL_FACTS_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    $attachments = $this->getItems($data, 'attachments');
    if (empty($attachments)) {
      return [];
    }
    $attachment_facts = $this->getItems($data, 'attachmentFacts');
    // If we have found attachments, also load the total facts.
    $this->addAttachmentFacts($attachments, $attachment_facts);

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->setCache($cache_key, $processed_attachments);
    return $processed_attachments;
  }

  /**
   * Get attachments by object type and id, optionally filtered.
   *
   * @param string $entity_type
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
  public function getAttachmentsByObject($entity_type, $entity_ids, $attachment_types = NULL) {
    $entity_ids = (array) $entity_ids;
    $attachment_types = array_filter((array) $attachment_types);
    sort($entity_ids);

    $cache_key = $this->getCacheKey([
      'entity_type' => $entity_type,
      'entity_ids' => $entity_ids,
    ] + $attachment_types);
    $attachments = $this->getCache($cache_key);
    if ($attachments) {
      return $attachments;
    }

    $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
    $filters = [
      'EntityMainType: { eq: "' . $type_filter_value . '" }',
      'EntityId: { in: [' . implode(',', $entity_ids) . '] }',
    ];
    if (!empty($attachment_types)) {
      $filters[] = 'AttachmentType: { in: ["' . implode('", "', $attachment_types) . '"] }';
    }

    $filter = implode(' ', $filters);
    $payload = "
      attachments (first: 10000, filter: {
        $filter
      }) {
        items {" . DataAttachment::GRAPHQL_DIMENSION_ITEMS . "}
      }
      ";
    $data = $this->fabricQuery->query($payload);
    $attachments = $this->getItems($data, 'attachments');
    if (empty($attachments)) {
      return [];
    }

    // If we have found attachments, also load the total facts.
    $this->addAttachmentFacts($attachments);

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->setCache($cache_key, $processed_attachments);
    return $processed_attachments;
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
   * @param bool $fetch_facts
   *   Whether to fetch the attachment facts too.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of attachment objects for the given context.
   */
  public function getAttachmentsForPlan(int $plan_id, ?ContentEntityInterface $context_object = NULL, array $filter = [], $fetch_facts = FALSE) {
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $plan_id,
      'context_type' => $context_object?->bundle() ?? NULL,
      'context_id' => $context_object?->id() ?? NULL,
      'fetch_facts' => (int) $fetch_facts,
    ] + $filter));
    $attachments = $this->getCache($cache_key);
    if ($attachments) {
      return $attachments;
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
    $query_filters = array_filter([
      'PlanId: { eq: ' . $plan_id . '}',
      $type_filter_value ? 'EntityMainType: { eq: "' . $type_filter_value . '" }' : NULL,
    ]);
    $attachment_types = !empty($filter['AttachmentType']) ? (array) $filter['AttachmentType'] : NULL;
    unset($filter['AttachmentType']);
    if (!empty($attachment_types)) {
      $query_filters[] = 'AttachmentType: { in: ["' . implode('", "', $attachment_types) . '"] }';
    }

    $query_filter = implode(' ', $query_filters);
    $payload = "
      attachments (first: 10000, filter: {
        $query_filter
      }) {
        items {" . DataAttachment::GRAPHQL_DIMENSION_ITEMS . "}
      }
      ";
    $data = $this->fabricQuery->query($payload);
    $attachments = $this->getItems($data, 'attachments');
    if (empty($attachments)) {
      return [];
    }

    if (!empty($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
    }

    if ($fetch_facts) {
      $this->addAttachmentFacts($attachments);
    }

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->setCache($cache_key, $processed_attachments);
    return $processed_attachments;
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
  public function getAttachmentsByPlan(array $plan_ids, $attachment_types = NULL) {
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
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   The plan entity objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities) {
    if (empty($entities)) {
      return [];
    }

    $entity_ids = [];
    foreach ($entities as $entity) {
      if (!$entity instanceof PlanEntityInterface) {
        continue;
      }
      $entity_ids[$entity->getEntityType()] = $entity_ids[$entity->getEntityType()] ?? [];
      $entity_ids[$entity->getEntityType()][] = $entity->id();
    }

    $attachments = [];

    $filters = [];
    foreach (array_keys($entity_ids) as $entity_type) {
      $type_filter_value = $this->getEntityTypeFilterValue($entity_type);
      if (!$type_filter_value) {
        continue;
      }
      $filters[] = '{ EntityMainType: { eq: "' . $type_filter_value . '" } EntityId:  { in: [' . implode(',', $entity_ids[$entity_type]) . '] } }';
    }

    if (empty($filters)) {
      return [];
    }

    $payload = "
      attachments (first: 10000, filter: {
        or: [" . implode('', $filters) . "]
        AttachmentType:  { in: [\"Caseload\", \"Indicator\"] }
      }) {
        items {" . DataAttachment::GRAPHQL_DIMENSION_ITEMS . "}
      }
      ";
    $data = $this->fabricQuery->query($payload);
    $attachments = $this->getItems($data, 'attachments');
    if (empty($attachments)) {
      return [];
    }

    // If we have found attachments, also load the total facts.
    $this->addAttachmentFacts($attachments);

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    return $processed_attachments;
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
      $attachment_ids = array_map(fn ($item) => $item->Id, $attachments);
      $facts_payload = "
        attachmentFacts (first: 100000, filter: {
          IsTotal: { eq: true }
          AttachmentId:  {
            in: [" . implode(',', $attachment_ids) . "]
          }
        }) {
          items {" . AttachmentFact::GRAPHQL_FACTS_ITEMS . " }
        }
        ";
      $data = $this->fabricQuery->query($facts_payload);
      $attachment_facts = $this->getItems($data, 'attachmentFacts');
    }

    foreach ($attachment_facts as $attachment_fact) {
      $attachment_id = $attachment_fact->AttachmentId;
      $attachments[$attachment_id]->totals = $attachments[$attachment_id]->totals ?? [];
      $attachments[$attachment_id]->totals[$attachment_fact->Id] = $attachment_fact;
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
    $payload = "
      attachmentFacts (filter: {
        IsTotal: { eq: false }
        AttachmentId:  {
          eq: {$attachment_id}
        }
      }) {
        items {" . AttachmentFact::GRAPHQL_FACTS_ITEMS . " }
      }";
    return $this->getItems($this->fabricQuery->query($payload), 'attachmentFacts') ?: [];
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
