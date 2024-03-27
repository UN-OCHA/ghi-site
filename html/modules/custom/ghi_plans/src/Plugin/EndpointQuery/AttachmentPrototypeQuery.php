<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachment prototypes.
 *
 * @EndpointQuery(
 *   id = "attachment_prototype_query",
 *   label = @Translation("Attachment prototype query"),
 *   endpoint = {
 *     "api_key" = "plan/attachment-prototype/{attachment_prototype_id}",
 *     "version" = "v2"
 *   }
 * )
 */
class AttachmentPrototypeQuery extends EndpointQueryBase {

  /**
   * Get an attachment prototype by ID.
   *
   * @param int $prototype_id
   *   The id of the prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The processed attachment prototype object.
   */
  public function getPrototypeById($prototype_id) {
    $data = $this->getData(['attachment_prototype_id' => $prototype_id]);
    if (empty($data)) {
      return NULL;
    }

    if ($data->id == $prototype_id) {
      return new AttachmentPrototype($data);
    }
  }

}
