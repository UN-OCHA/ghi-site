<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

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
   * @var array
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
      $this->plans[$plan_object->id] = [
        'id' => $plan_object->id,
        'name' => $plan_object->name,
        'plan_type' => (object) [
          'id' => $plan_object->planType->id,
          'name' => $plan_object->planType->name,
          'include_totals' => $plan_object->planType->includeTotals,
        ],
        'total_funding' => $plan_object->funding->totalFunding ?? 0,
        'total_requirements' => $plan_object->requirements->requirements ?? 0,
        'funding_progress' => $plan_object->funding->progress ?? 0,
        'caseloads' => $plan_object->caseLoads[0]->totals ?? [],
      ];
    }

    uasort($this->plans, function ($a, $b) {
      return strnatcmp($a['name'], $b['name']);
    });
  }

  /**
   * Get plans.
   *
   * @return array
   *   An array of plan items.
   */
  public function getPlans() {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    return $this->plans;
  }

}
