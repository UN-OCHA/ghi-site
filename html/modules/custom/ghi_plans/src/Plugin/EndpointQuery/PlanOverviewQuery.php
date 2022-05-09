<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan overview data.
 *
 * @EndpointQuery(
 *   id = "plan_overview_query",
 *   label = @Translation("Plan overview query"),
 *   endpoint = {
 *     "public" = "fts/flow/plan/overview/progress/{year}",
 *     "authenticated" = "plan/overview/{year}",
 *     "version" = "v2"
 *   }
 * )
 */
class PlanOverviewQuery extends EndpointQueryBase {

  /**
   * The fetched and processed plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   */
  private $plans = NULL;

  /**
   * Retrieve plan data.
   */
  private function retrievePlans() {
    $this->plans = [];
    $data = $this->getData();

    if (empty($data) || empty($data->plans)) {
      return;
    }

    $plan_objects = $data->plans;
    foreach ($plan_objects as $plan_object) {
      $plan = new PlanOverviewPlan($plan_object);
      $this->plans[$plan->id()] = $plan;
    }

    uasort($this->plans, function ($a, $b) {
      return strnatcmp($a->name, $b->name);
    });
  }

  /**
   * Get plans.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   An array of plan objects.
   */
  public function getPlans() {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    return $this->plans;
  }

}
