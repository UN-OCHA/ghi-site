<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides a query plugin for basic plan data.
 *
 * @EndpointQuery(
 *   id = "plan_basic_query",
 *   label = @Translation("Plan basic query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}",
 *     "authenticated" = "plan/{plan_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "version" = "current",
 *       "content" = "basic",
 *     }
 *   }
 * )
 */
class PlanBasicQuery extends EndpointQueryBase {

  use PlanVersionArgument;
  use SimpleCacheTrait;
  use StringTranslationTrait;

  /**
   * Get the base data for a plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan
   *   An API plan object.
   */
  public function getBaseData($plan_id) {
    $cache_key = $this->getCacheKey([
      'plan_id' => $plan_id,
      'authenticated' => $this->isAutenticatedEndpoint,
    ]);
    $base_data = $this->cache($cache_key);
    if ($base_data !== NULL) {
      return $base_data;
    }
    $data = $this->getData(['plan_id' => $plan_id], ['version' => $this->getPlanVersionArgumentForPlanId($plan_id)]);
    $base_data = !empty($data) ? new Plan($data) : FALSE;

    $this->cache($cache_key, $base_data);
    return $base_data;
  }

}
