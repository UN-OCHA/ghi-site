<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for attachment prototypes of plans.
 *
 * @EndpointQuery(
 *   id = "plan_attachment_prototype_query",
 *   label = @Translation("Plan attachment prototype query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}/attachment-prototype",
 *     "version" = "v2"
 *   }
 * )
 */
class PlanAttachmentPrototypeQuery extends EndpointQueryBase {

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

  /**
   * Get all data attachment prototypes for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototype objects.
   */
  public function getDataPrototypesForPlan($plan_id) {
    $data = $this->getData(['plan_id' => $plan_id]);
    if (empty($data)) {
      return [];
    }

    $prototypes = [];
    foreach ($data as $prototype) {
      if (AttachmentPrototype::isDataType($prototype)) {
        $prototypes[$prototype->id] = new AttachmentPrototype($prototype);
      }
    }
    return $prototypes;
  }

}
