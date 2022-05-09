<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides a query plugin for plan entities.
 *
 * @EndpointQuery(
 *   id = "plan_basic_query",
 *   label = @Translation("Plan basic query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}",
 *     "authenticated" = "plan/{plan_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "content" = "basic",
 *     }
 *   }
 * )
 */
class PlanBasicQuery extends EndpointQueryBase {

  use SimpleCacheTrait;
  use StringTranslationTrait;

  /**
   * Get the base data for a plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return object
   *   An array of attachment objects for the given context.
   */
  public function getBaseData($plan_id) {
    $cache_key = $this->getCacheKey(['plan_id' => $plan_id]);
    $base_data = $this->cache($cache_key);
    if ($base_data !== NULL) {
      return $base_data;
    }
    $this->setPlaceholder('plan_id', $plan_id);
    $data = $this->getData();
    $base_data = !empty($data) ? new Plan($data) : FALSE;

    $this->cache($cache_key, $base_data);
    return $base_data;
  }

}
