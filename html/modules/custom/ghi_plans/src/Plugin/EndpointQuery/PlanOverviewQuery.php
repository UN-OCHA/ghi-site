<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_base_objects\ApiObjects\Plan;
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
   * @var \Drupal\ghi_base_objects\ApiObjects\Plan[]
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
      $plan = new Plan($plan_object);
      $this->plans[$plan->getId()] = $plan;
    }

    uasort($this->plans, function ($a, $b) {
      return strnatcmp($a->getName(), $b->getName());
    });
  }

  /**
   * Get plans.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getPlans() {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    return $this->plans;
  }

}
