<?php

namespace Drupal\hpc_api\Query;

/**
 * Get plan information.
 */
class EndpointPlanQuery extends EndpointQuery {

  /**
   * Get plan.
   */
  public function getPlan($plan_id, $public = TRUE, $hid = NULL) {
    if ($public) {
      return $this->getPlanPublic($plan_id);
    }

    return $this->getPlanPrivate($plan_id, $hid);
  }

  /**
   * Get public info from a plan.
   */
  public function getPlanPublic($plan_id) {
    $this->setEndpoint('public/plan/id/' . $plan_id);

    $data = $this->getData();

    $plan = [
      'id' => $data->id,
      'name' => $data->planVersion->name,
      'code' => $data->planVersion->code,
      'start_date' => $data->planVersion->startDate ?? '',
      'end_date' => $data->planVersion->endDate ?? '',
      'orig_requirements' => $data->origRequirements ?? 0,
      'revised_requirements' => $data->revisedRequirements ?? 0,
      'categories' => [],
      'locations' => [],
    ];

    foreach ($data->categories as $category) {
      $plan['categories'][] = (object) [
        'id' => $category->id,
        'name' => $category->name,
        'code' => $category->code,
      ];
    }

    foreach ($data->locations as $location) {
      $plan['locations'][] = (object) [
        'id' => $location->id,
        'name' => $location->name,
        'admin_level' => $location->adminLevel,
        'iso3' => $location->iso3,
        'pcode' => $location->pcode,
      ];
    }

    return (object) $plan;
  }

  /**
   * Get private info from a plan.
   */
  public function getPlanPrivate($plan_id, $hid) {
  }

  /**
   * Get plan key figures.
   */
  public function getPlanKeyFigures($plan_id) {
    // Get plan.
    $data = $this->getPlan($plan_id);
    $total_requirement = $data['revised_requirements'];

    // Clear all arguments.
    $this->setEndpointArguments([]);

    // Get flow.
    $data = $this->getFlowsByPlan($plan_id);
    $funding_total = $data->incoming->fundingTotal;

    $unmet_requirement = ($total_requirement - $funding_total);
    $funded_average = round(($funding_total * 100) / $total_requirement, 1);

    return (object) [
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

    $years = [];
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

    // @todo filter data.
    return $this->getData();
  }

  /**
   * Get plans by country.
   */
  public function getPlansByCountryIso3($iso3 = '') {
    $this->setEndpoint('public/plan/country/' . $iso3);

    // @todo filter data.
    return $this->getData();
  }

  /**
   * Get flows by plan.
   */
  public function getFlowsByPlan($plan_id) {
    $this->setEndpoint('public/fts/flow');
    $this->setEndpointArgument('planId', $plan_id);

    // @todo filter data.
    return $this->getData();
  }

  /**
   * Get flows by plan grouped by cluster.
   */
  public function getFlowsByPlanGroupByCluster($plan_id) {
    $this->setEndpoint('public/fts/flow');
    $this->setEndpointArgument('planId', $plan_id);
    $this->setEndpointArgument('groupby', 'Cluster');

    $data = $this->getData();

    $raw_clusters = $data->report3->fundingTotals->objects[0]->objectsBreakdown;
    usort($raw_clusters, function ($a, $b) {
      return $b->totalFunding - $a->totalFunding;
    });

    $clusters = [];
    foreach ($raw_clusters as $raw_cluster) {
      $clusters[] = (object) [
        'id' => $raw_cluster->id,
        'name' => $raw_cluster->name,
        'type' => $raw_cluster->type,
        'direction' => $raw_cluster->direction ?? '',
        'total_funding' => $raw_cluster->totalFunding ?? 0,
        'single_funding' => $raw_cluster->singleFunding ?? 0,
        'overlap_funding' => $raw_cluster->overlapFunding ?? 0,
        'shared_funding' => $raw_cluster->sharedFunding ?? 0,
        'on_boundary_funding' => $raw_cluster->onBoundaryFunding ?? 0,
      ];
    }

    return $clusters;
  }

  /**
   * Get flow sources by plan.
   */
  public function getFlowSourcesByPlan($plan_id) {
    $this->setEndpoint('fts/flow/plan/summary/sources/' . $plan_id);

    $data = $this->getData();

    $raw_sources = $data[0]->funding_sources;
    usort($raw_sources, function ($a, $b) {
      return $b->total_funding - $a->total_funding;
    });

    $sources = [];
    foreach ($raw_sources as $raw_source) {
      $sources[] = (object) [
        'id' => $raw_source->id,
        'name' => $raw_source->name,
        'type' => $raw_source->type,
        'direction' => $raw_source->direction ?? '',
        'total_funding' => $raw_source->total_funding ?? 0,
      ];
    }

    return $sources;
  }

}
