<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
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

  use SimpleCacheTrait;

  /**
   * Get the reporting periods for a plan.
   *
   * @return array
   *   An array of reporting periods, keyed by period id and sorted by period
   *   number.
   */
  public function getReportingPeriods() {
    $cache_key = $this->getCacheKey($this->getPlaceholders());
    $periods = $this->cache($cache_key);
    if ($periods) {
      return $periods;
    }

    $data = $this->getData();
    if (!$data) {
      return [];
    }
    $period_ids = array_map(function ($period) {
      return $period->id;
    }, $data);
    $periods = array_combine($period_ids, $data);
    ArrayHelper::sortObjectsByNumericProperty($data, 'periodNumber', EndpointQuery::SORT_ASC);

    $this->cache($cache_key, $periods);
    return $periods;
  }

  /**
   * Get a specific reporting period for a plan.
   *
   * @return object
   *   The reporting period.
   */
  public function getReportingPeriod($id) {
    $periods = $this->getReportingPeriods();
    return $periods[$id] ?? NULL;
  }

}
