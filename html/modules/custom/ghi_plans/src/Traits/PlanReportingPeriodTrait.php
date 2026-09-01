<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Trait to help with retrieving reporting periods for a plan.
 */
trait PlanReportingPeriodTrait {

  use PlanQueryTrait;

  /**
   * Get a single specified reporting period object for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param int|string $period_id
   *   The reporting period id or the string 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  public static function getPlanReportingPeriod($plan_id, $period_id): ?PlanReportingPeriod {
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
  public static function getPlanReportingPeriods($plan_id, $limit_to_published = FALSE): array {
    $periods = self::getPlanQuery()?->getPlanReportingPeriods($plan_id) ?? [];
    $periods = $limit_to_published ? array_filter($periods, function ($period) {
      return $period->isPublished();
    }) : $periods;
    ArrayHelper::sortObjectsByMethod($periods, 'getPeriodNumber');
    return $periods;
  }

  /**
   * Get the id of the last published reporting period.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return int|null
   *   The id of the latest published reporting period or NULL.
   */
  public static function getLatestPublishedReportingPeriod(int $plan_id): ?int {
    $plan = BaseObjectHelper::getBaseObjectFromOriginalId($plan_id, 'plan');
    return $plan instanceof Plan ? $plan->getLastPublishedReportingPeriodId() : NULL;
  }

}
