<?php

namespace Drupal\hpc_api\Query;

/**
 * Get plan information.
 */
class EndpointPlanQuery extends EndpointQuery {

  /**
   * Get plan.
   */
  public function getPlan($plan_id) {
    $this->setEndpoint('public/plan/id/' . $plan_id);

    return $this->getData();
  }

  /**
   * Get plan key figures.
   */
  public function getPlanKeyFigures($plan_id) {
    $data = $this->getPlan($plan_id);
    $total_requirement = $data->revisedRequirements;

    // Clear all arguments.
    $this->setEndpointArguments([]);

    $data = $this->getFlowByPlan($plan_id);
    $funding_total = $data->incoming->fundingTotal;

    $unmet_requirement = ($total_requirement - $funding_total);
    $funded_average = round(($funding_total * 100) / $total_requirement, 1);

    return [
      'total_requirement' => $total_requirement,
      'funding_total' => $funding_total,
      'unmet_requirement' => $unmet_requirement,
      'funded_average' => $funded_average,
    ];
  }

  /**
   * Get plan years.
   */
  public function getPlanYears($plan_id) {
    $this->setEndpoint('public/plan/id/' . $plan_id);

    $data = $this->getData();

    $years = array();
    if (isset($data->years) && is_array($data->years)) {
      foreach ($data->years as $year_info) {
        $years[] = $year_info->year;
      }
    }

    return $years;
  }

  /**
   * Get plans by year.
   */
  public function getPlansByYear($year = '') {
    $this->setEndpoint('public/plan/year/' . $year);

    return $this->getData();
  }

  /**
   * Get plans by country.
   */
  public function getPlansByCountryIso3($iso3 = '') {
    $this->setEndpoint('public/plan/country/' . $iso3);

    return $this->getData();
  }

  /**
   * Get flow by plan.
   */
  public function getFlowByPlan($plan_id) {
    $this->setEndpoint('public/fts/flow');
    $this->setEndpointArgument('planId', $plan_id);

    return $this->getData();
  }

  /**
   * Get flow by plan grouped by cluster.
   */
  public function getFlowByPlanGroupByCluster($plan_id) {
    $this->setEndpoint('public/fts/flow');
    $this->setEndpointArgument('planId', $plan_id);
    $this->setEndpointArgument('groupby', 'Cluster');

    $data = $this->getData();

    $clusters = $data->report3->fundingTotals->objects[0]->objectsBreakdown;
    usort($clusters, function ($a, $b) {
      return $b->totalFunding - $a->totalFunding;
    });

    return $clusters;
  }

  /**
   * Get flow sources by plan.
   */
  public function getFlowSourcesByPlan($plan_id) {
    $this->setEndpoint('fts/flow/plan/summary/sources/' . $plan_id);

    $data = $this->getData();

    $countries = $data[0]->funding_sources;
    usort($countries, function ($a, $b) {
      return $b->total_funding - $a->total_funding;
    });

    return $countries;
  }
}
