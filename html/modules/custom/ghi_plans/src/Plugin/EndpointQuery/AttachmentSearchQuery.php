<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides a query plugin for attachment search.
 *
 * @EndpointQuery(
 *   id = "attachment_search_query",
 *   label = @Translation("Attachment search query"),
 *   endpoint = {
 *     "public" = "public/attachment",
 *     "authenticated" = "attachment",
 *     "version" = "v2",
 *     "query" = {
 *       "version" = "current",
 *       "disaggregation" = "false",
 *     }
 *   }
 * )
 */
class AttachmentSearchQuery extends EndpointQueryBase {

  use AttachmentFilterTrait;
  use PlanVersionArgument;
  use SimpleCacheTrait;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $this->endpointQuery->setPlaceholders($placeholders);
    if ($plan_id = $this->getPlaceholder('plan_id')) {
      $query_args['version'] = $this->getPlanVersionArgumentForPlanId($plan_id);
    }
    return parent::getData($placeholders, $query_args);
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
    $query_args = [
      'attachmentIds' => implode(',', array_filter($attachment_ids)),
    ];
    if (!$disaggregated) {
      $query_args['disaggregation'] = 'false';
    }
    $cache_key = $this->getCacheKey($query_args);
    $attachments = $this->cache($cache_key);
    if ($attachments) {
      return $attachments;
    }
    $attachments = $this->getData([], $query_args);
    if (empty($attachments)) {
      return [];
    }

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->cache($cache_key, $processed_attachments);
    return $processed_attachments;
  }

  /**
   * Get attachments by object type and id, optionally filtered.
   *
   * @param string $object_type
   *   The object type for an attachment, either "governingEntity" or
   *   "planEntity".
   * @param array|int $object_ids
   *   The object ids that the attachments should belong to.
   * @param array $filter
   *   An optional filter array, e.g.:
   *   [
   *     'type' => 'caseload',
   *   ].
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface[]
   *   The matching (processed) attachment objects, keyed by the attachment id.
   */
  public function getAttachmentsByObject($object_type, $object_ids, array $filter = NULL) {
    $object_ids = (array) $object_ids;
    sort($object_ids);

    $version = 'current';
    if ($object_type == 'plan' && count($object_ids) == 1) {
      // Use the correct plan version argument.
      $version = $this->getPlanVersionArgumentForPlanId(reset($object_ids));
    }

    $cache_key = $this->getCacheKey([
      'object_type' => $object_type,
      'object_ids' => $object_ids,
      'version' => $version,
    ] + (array) $filter);
    $attachments = $this->cache($cache_key);
    if ($attachments) {
      return $attachments;
    }
    $attachments = $this->getData([], [
      'objectType' => $object_type,
      'objectIds' => implode(',', (array) $object_ids),
      'version' => $version,
    ]);

    if (empty($attachments)) {
      return [];
    }

    if (is_array($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
      if (empty($attachments)) {
        return [];
      }
    }

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->cache($cache_key, $processed_attachments);
    return $processed_attachments;
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
      return NULL;
    }
    $entity_ids = [
      'plan' => [],
      'plan_entity' => [],
      'governing_entity' => [],
    ];
    foreach ($entities as $entity) {
      if ($entity instanceof Plan) {
        $entity_ids['plan'][] = $entity->id;
      }
      if ($entity instanceof PlanEntity) {
        $entity_ids['plan_entity'][] = $entity->id;
      }
      if ($entity instanceof GoverningEntity) {
        $entity_ids['governing_entity'][] = $entity->id;
      }
    }

    $attachments = [];
    if (!empty($entity_ids['plan'])) {
      $attachments = array_merge($attachments, $this->getAttachmentsByObject('plan', $entity_ids['plan']));
    }
    if (!empty($entity_ids['plan_entity'])) {
      $attachments = array_merge($attachments, $this->getAttachmentsByObject('planEntity', $entity_ids['plan_entity']));
    }
    if (!empty($entity_ids['governing_entity'])) {
      $attachments = array_merge($attachments, $this->getAttachmentsByObject('governingEntity', $entity_ids['governing_entity']));
    }
    return $attachments;
  }

}
