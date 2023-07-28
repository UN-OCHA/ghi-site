<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\ApiObjects\PlanPrototype;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan entities.
 *
 * @EndpointQuery(
 *   id = "plan_prototype_query",
 *   label = @Translation("Plan prototype query"),
 *   endpoint = {
 *     "api_key" = "plan/{plan_id}/entity-prototype",
 *     "version" = "v2",
 *   }
 * )
 */
class PlanPrototypeQuery extends EndpointQueryBase {

  use StringTranslationTrait;

  /**
   * Get the prototype for a plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanPrototype
   *   A plan prototype object.
   */
  public function getPrototype($plan_id) {
    $cache_key = $this->getCacheKey(['plan_id' => $plan_id]);
    $prototype = $this->getCache($cache_key);
    if ($prototype !== NULL) {
      return $prototype;
    }
    $this->setPlaceholder('plan_id', $plan_id);
    $data = $this->getData();
    $prototype = !empty($data) ? new PlanPrototype($data) : FALSE;

    $this->setCache($cache_key, $prototype);
    return $prototype;
  }

}
