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

  /**
   * Get the caseload total values for the supplied types.
   *
   * @param array $types
   *   The types of caseload of which the sum is to be returned. The keys
   *   should be the expected metric type, the values the metric label.
   *
   * @return array
   *   An array keyed by the type and valued by the total sum of that type
   */
  public function getCaseloadTotalValues(array $types) {
    $plans = $this->getPlans();

    // Setting up the array keyed by the types and values as 0.
    $caseload_totals = array_fill_keys(array_keys($types), 0);

    // Load the override settings per plan.
    $attachment_overrides = $this->getPlanCaseloadOverridesByPlanId();

    // Since all plans are now populated with people in need and target values,
    // the total GHO people in need and people targeted can be calculated by
    // summing these plans caseload values where the planType has the property
    // includeTotals = true from this endpoint:
    // https://api.hpc.tools/v2/plan/overview/{year}?version=latest
    if (!empty($plans)) {
      foreach ($plans as $plan) {
        // Include plans where the planType has includeTotals=true.
        if (!$plan->isTypeIncluded()) {
          continue;
        }

        // Check caseLoads and respective totals property has value.
        $caseload = $plan->getPlanCaseload($attachment_overrides[$plan->id()] ?? NULL);
        if (empty($caseload) || empty($caseload->totals)) {
          continue;
        }

        foreach ($types as $type => $type_label) {
          $value = $plan->getCaseloadValue($type, $type_label);
          $caseload_totals[$type] += $value ?? 0;
        }
      }
    }

    return $caseload_totals;
  }

  /**
   * Get specific plan caseload overrides keyed by plan id.
   *
   * Per plan base object, a specific caseload can be specified in the backend,
   * which should be used whenever data from the plan level caseload should be
   * shown. Here we load them in one go to have them easily available.
   *
   * @return array
   *   An array with the attachment ids of specific plan level caseload
   *   attachments, keyed by the plan id.
   */
  private function getPlanCaseloadOverridesByPlanId() {
    $plans = $this->getPlans();
    $caseload_overrides = [];
    if (empty($plans)) {
      return $caseload_overrides;
    }
    $result = \Drupal::entityTypeManager()
      ->getStorage('base_object')
      ->loadByProperties([
        'type' => 'plan',
        'field_original_id' => array_keys($plans),
      ]);
    if (empty($result)) {
      return $result;
    }
    foreach ($result as $plan) {
      $attachment_id = $plan->field_plan_caseload->attachment_id;
      $caseload_overrides[$plan->field_original_id->value] = $attachment_id !== NULL ? (int) $attachment_id : NULL;
    }
    return array_filter($caseload_overrides);
  }

}
