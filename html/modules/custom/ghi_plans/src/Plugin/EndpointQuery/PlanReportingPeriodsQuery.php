<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

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
   * @return array
   *   An array of reporting periods, keyed by period id and sorted by period
   *   number.
   */
  public function getReportingPeriods() {
    $data = $this->getData();
    if (!$data) {
      return [];
    }
    $period_ids = array_map(function ($period) {
      return $period->id;
    }, $data);
    $periods = array_combine($period_ids, $data);
    ArrayHelper::sortObjectsByNumericProperty($data, 'periodNumber', EndpointQuery::SORT_ASC);
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
