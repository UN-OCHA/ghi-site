<?php

namespace Drupal\ghi_plans\Traits;

/**
 * Trait to help with retrieving reporting periods for a plan.
 */
trait PlanReportingPeriodTrait {

  /**
   * Get a single specified reporting period object for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param int $period_id
   *   The reporting period id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod
   *   A reporting period object.
   */
  public static function getPlanReportingPeriod($plan_id, $period_id) {
    if ($period_id == 'latest') {
      $period_id = self::getLatestPublishedReportingPeriod($plan_id);
    }
    if (!$period_id) {
      return NULL;
    }
    $periods = self::getPlanReportingPeriods($plan_id, FALSE);
    return array_key_exists($period_id, $periods) ? $periods[$period_id] : NULL;
  }

  /**
   * Get the reporting periods for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param bool $limit_to_published
   *   Whether to limit the reporting periods to the ones that are published.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of monitoring period objects.
   */
  public static function getPlanReportingPeriods($plan_id, $limit_to_published = FALSE) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanReportingPeriodsQuery $query */
    $query = self::getEndpointQueryManager()->createInstance('plan_reporting_periods_query');
    if (!$query) {
      return [];
    }
    $query->setPlaceholder('plan_id', $plan_id);
    $periods = $query->getReportingPeriods();
    if ($limit_to_published && $last_published_period = self::getLatestPublishedReportingPeriod($plan_id)) {
      $periods = array_filter($periods, function ($period) use ($last_published_period) {
        return $period->id() <= $last_published_period;
      });
    }
    return $periods;
  }

  /**
   * Get the id of the last published reporting period.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return int
   *   The id of the latest published reporting period.
   */
  public static function getLatestPublishedReportingPeriod($plan_id) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanBasicQuery $query */
    $query = self::getEndpointQueryManager()->createInstance('plan_basic_query');
    if (!$query) {
      return NULL;
    }
    $plan_data = $query->getBaseData($plan_id);
    return $plan_data ? $plan_data->last_published_period : NULL;
  }

  /**
   * Get the endpoint query manager service.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

}
