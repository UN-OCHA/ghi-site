<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Provides a query plugin for attachments.
 *
 * @EndpointQuery(
 *   id = "plan_reporting_periods_query",
 *   label = @Translation("Plan reporting periods query"),
 *   endpoint = {
 *     "api_key" = "plan/{plan_id}/reportingPeriod",
 *     "version" = "v2"
 *   }
 * )
 */
class PlanReportingPeriodsQuery extends EndpointQueryBase {

  /**
   * Get the reporting periods for a plan.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of reporting periods, keyed by period id and sorted by period
   *   number.
   */
  public function getReportingPeriods($plan_id) {
    $placeholders = ['plan_id' => $plan_id];
    $cache_key = $this->getCacheKey($placeholders);
    $periods = $this->getCache($cache_key);
    if ($periods) {
      return $periods;
    }

    $data = $this->getData($placeholders);
    if (!$data) {
      return [];
    }
    ArrayHelper::sortObjectsByNumericProperty($data, 'periodNumber', EndpointQuery::SORT_ASC);
    $periods = [];
    foreach ($data as $period) {
      $periods[$period->id] = new PlanReportingPeriod($period);
    }
    $this->setCache($cache_key, $periods);
    return $periods;
  }

  /**
   * Get a specific reporting period for a plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param int $id
   *   The reporting period id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The reporting period or NULL.
   */
  public function getReportingPeriod($plan_id, $id): PlanReportingPeriod|null {
    $periods = $this->getReportingPeriods($plan_id);
    return $periods[$id] ?? NULL;
  }

}
