<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

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
   * @return array
   *   The matching and processed attachment objects.
   */
  public function getAttachmentsByObject($object_type, $object_ids, array $filter = NULL) {
    $attachments = $this->getData([], [
      'objectType' => $object_type,
      'objectIds' => implode(',', (array) $object_ids),
    ]);

    if (empty($attachments)) {
      return NULL;
    }

    if (is_array($filter)) {
      $attachments = $this->filterAttachments($attachments, $filter);
      if (empty($attachments)) {
        return NULL;
      }
    }

    return array_map(function ($attachment) {
      return AttachmentHelper::processAttachment($attachment);
    }, $attachments);
  }

}
