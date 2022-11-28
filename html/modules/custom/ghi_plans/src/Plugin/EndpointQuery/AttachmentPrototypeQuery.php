<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for clusters.
 *
 * @EndpointQuery(
 *   id = "attachment_prototype_query",
 *   label = @Translation("Attachment prototype query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}/attachment-prototype",
 *     "authenticated" = "plan/{plan_id}/attachment-prototype",
 *     "version" = "v2"
 *   }
 * )
 */
class AttachmentPrototypeQuery extends EndpointQueryBase {

  /**
   * Get an attachment prototype by plan and prototype ID.
   *
   * @param int $plan_id
   *   The id of the plan to which a prototype belongs.
   * @param int $prototype_id
   *   The id of the prototype.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The processed attachment prototype object.
   */
  public function getPrototypeByPlanAndId($plan_id, $prototype_id) {
    $data = $this->getData(['plan_id' => $plan_id]);
    if (empty($data)) {
      return NULL;
    }

    foreach ($data as $prototype) {
      if ($prototype->id == $prototype_id) {
        return new AttachmentPrototype($prototype);
      }
    }
    return NULL;
  }

}
