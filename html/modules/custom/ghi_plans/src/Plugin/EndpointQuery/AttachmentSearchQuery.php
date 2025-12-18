<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachment search.
 *
 * @EndpointQuery(
 *   id = "attachment_search_query",
 *   label = @Translation("Attachment search query"),
 *   endpoint = {
 *     "public" = "public/attachment",
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
    $attachments = $this->getCache($cache_key);
    if ($attachments) {
      return $attachments;
    }
    $attachments = $this->getData([], $query_args);
    if (empty($attachments)) {
      return [];
    }

    $processed_attachments = AttachmentHelper::processAttachments($attachments);
    $this->setCache($cache_key, $processed_attachments);
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
  public function getAttachmentsByObject($object_type, $object_ids, ?array $filter = NULL) {
    $object_ids = (array) $object_ids;
    sort($object_ids);

    $version = 'current';
    $cache_key = $this->getCacheKey([
      'object_type' => $object_type,
      'object_ids' => $object_ids,
      'version' => $version,
    ] + (array) $filter);
    $attachments = $this->getCache($cache_key);
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
    $this->setCache($cache_key, $processed_attachments);
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

    $entity_ids = [];
    foreach ($entities as $entity) {
      if (!$entity instanceof PlanEntityInterface) {
        continue;
      }
      $entity_ids[$entity->getEntityType()] = $entity_ids[$entity->getEntityType()] ?? [];
      $entity_ids[$entity->getEntityType()][] = $entity->id();
    }

    $attachments = [];
    foreach ($entity_ids as $type => $ids) {
      $attachments += $this->getAttachmentsByObject($type, $ids);
    }
    return $attachments;
  }

}
