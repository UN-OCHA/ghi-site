<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "attachment_query",
 *   label = @Translation("Plan entities query"),
 *   endpoint = {
 *     "public" = "public/attachment/{attachment_id}",
 *     "authenticated" = "attachment/{attachment_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "version" = "current",
 *       "disaggregation" = "false",
 *     }
 *   }
 * )
 */
class AttachmentQuery extends EndpointQueryBase {

  /**
   * Get an attachment by id.
   *
   * @param int $attachment_id
   *   The attachment id to query.
   *
   * @return object
   *   The processed attachment object.
   */
  public function getAttachment($attachment_id) {
    $data = $this->getData(['attachment_id' => $attachment_id]);
    if (empty($data)) {
      return NULL;
    }

    return AttachmentHelper::processAttachment($data);
  }

}
