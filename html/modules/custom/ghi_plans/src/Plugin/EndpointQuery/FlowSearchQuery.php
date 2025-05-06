<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\CommonHelper;

/**
 * Provides a query plugin for flow search.
 *
 * @EndpointQuery(
 *   id = "flow_search_query",
 *   label = @Translation("Flow search query"),
 *   endpoint = {
 *     "public" = "fts/flow/custom-search",
 *     "version" = "v1"
 *   }
 * )
 */
class FlowSearchQuery extends EndpointQueryBase {

  /**
   * Search with arguments.
   *
   * @param array $arguments
   *   The arguments for the query.
   *
   * @return object
   *   The result set.
   */
  public function search(array $arguments) {
    $data = $this->getData([], $arguments);
    if (empty($data)) {
      return NULL;
    }
    return $data;
  }

  /**
   * Check if the current query is grouped.
   *
   * @return bool
   *   Whether the current search query is grouped.
   */
  private function isGrouped() {
    return !empty($this->endpointQuery->getEndpointArgument('groupby'));
  }

  /**
   * Static helper function to extract cluster ids for a flow search result.
   *
   * @param object $data
   *   The result object from a grouped flow search.
   * @param array $cluster_ids
   *   Optional some cluster ids to restrict to.
   *
   * @return int[]
   *   An array of cluster ids.
   */
  public function getClusterIds($data, ?array $cluster_ids = NULL) {
    if (!$this->isGrouped()) {
      return NULL;
    }
    $cluster_ids_requirements = [];
    if (!empty($data->requirements) && !empty($data->requirements->objects)) {
      $requirements_objects = $data->requirements->objects;

      if (!empty($cluster_ids)) {
        $requirements_objects = ArrayHelper::filterArray($requirements_objects, ['id' => $cluster_ids]);
      }
      $cluster_ids_requirements = !empty($requirements_objects) ? array_map(function ($object) {
        return (int) $object->id;
      }, $requirements_objects) : [];
    }

    // Extract and aggregate the funding.
    $cluster_ids_funding = [];
    if (!empty($data->report3->fundingTotals)) {
      $funding_objects = $data->report3->fundingTotals->objects[0]->objectsBreakdown;
      if (!empty($cluster_ids)) {
        $funding_objects = ArrayHelper::filterArray($funding_objects, ['id' => $cluster_ids]);
      }
      $cluster_ids_funding = !empty($funding_objects) ? array_unique(
        array_map(function ($object) {
          return (int) $object->id;
        }, $funding_objects)
      ) : [];
    }

    // Merge and make clusters unique.
    return array_values(
      array_filter(
        array_unique(
          array_merge($cluster_ids_requirements, $cluster_ids_funding)
        )
      )
    );
  }

  /**
   * Get the funding and requirements by cluster.
   *
   * @param object $data
   *   The result object from a grouped flow search.
   * @param array $cluster_ids
   *   Cluster ids to restrict to.
   *
   * @return array
   *   An array of funding data, mocked to be identical in structure to the
   *   result of PlanFundingSummaryQuery::getData()
   */
  public function getFundingDataByClusterIds($data, array $cluster_ids) {
    $funding_data = [
      'original_requirements' => NULL,
      'current_requirements' => NULL,
      'total_funding' => NULL,
      'funding_coverage' => NULL,
    ];
    $array_filter = ['id' => $cluster_ids];
    if (!empty($data->requirements) && !empty($data->requirements->objects)) {
      $requirements_objects = ArrayHelper::filterArray($data->requirements->objects, $array_filter);
      $funding_data['original_requirements'] = ArrayHelper::sumObjectsByProperty($requirements_objects, 'origRequirements');
      $funding_data['current_requirements'] = ArrayHelper::sumObjectsByProperty($requirements_objects, 'revisedRequirements');
    }
    if (!empty($data->report3->fundingTotals)) {
      $funding_objects = ArrayHelper::filterArray($data->report3->fundingTotals->objects[0]->objectsBreakdown, $array_filter);
      $funding_data['total_funding'] = ArrayHelper::sumObjectsByProperty($funding_objects, 'totalFunding');
    }
    $funding_data['funding_coverage'] = $funding_data['current_requirements'] ? 100 / $funding_data['current_requirements'] * $funding_data['total_funding'] : 0;
    $funding_data['funding_gap'] = $funding_data['current_requirements'] > $funding_data['total_funding'] ? $funding_data['current_requirements'] - $funding_data['total_funding'] : 0;
    return $funding_data;
  }

  /**
   * Get financial data per plan.
   *
   * @param int[] $plan_ids
   *   An array of plan ids to query.
   *
   * @return array
   *   An array of of arrays of financial data, keyed by the plan id.
   */
  public function getFinancialDataPerPlan(array $plan_ids) {
    $funding_data = array_map(function ($plan_id) {
      return [
        'current_requirements' => NULL,
        'total_funding' => NULL,
        'funding_coverage' => NULL,
      ];
    }, array_combine($plan_ids, $plan_ids));
    $data = $this->getData([], [
      'planId' => implode(',', $plan_ids),
      'groupBy' => 'plan',
    ]);
    $objects_breakdown = $data->report3?->fundingTotals?->objects[0]?->objectsBreakdown ?? [];
    $requirements = [];
    foreach ($data->requirements?->objects ?? [] as $object) {
      $requirements[$object->id] = $object?->revisedRequirements ?: $object->origRequirements;
    }
    foreach ($objects_breakdown as $object) {
      $plan_id = $object->id;
      $funding_data[$plan_id]['current_requirements'] = $requirements[$plan_id] ?: NULL;
      $funding_data[$plan_id]['total_funding'] = $object->totalFunding;
      $funding_data[$plan_id]['funding_coverage'] = CommonHelper::calculateRatio($object->totalFunding, $requirements[$plan_id] ?: 0) * 100;
    }
    return $funding_data;
  }

}
