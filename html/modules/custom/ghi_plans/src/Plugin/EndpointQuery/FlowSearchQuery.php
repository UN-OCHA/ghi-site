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
   * {@inheritdoc}
   */
  public function getClusterSummaryData() {
    $placeholders = $this->getPlaceholders();
    $query_args = [
      'planId' => $placeholders['plan_id'],
      'groupBy' => 'cluster',
      'report' => 3,
    ];
    $cache_key = $this->getCacheKey(['type' => 'cluster_summary'] + $this->getPlaceholders() + $query_args);
    if ($summary_data = $this->getCache($cache_key)) {
      return $summary_data;
    }
    $data = parent::getData($placeholders, $query_args);
    if (empty($data)) {
      $this->setCache($cache_key, []);
      return [];
    }

    $clusters = $data->report3?->fundingTotals?->objects[0]?->objectsBreakdown ?? [];
    $totals = $data->report3?->fundingTotals?->objects[0]?->totalBreakdown ?? NULL;
    if (empty($clusters) || empty($totals)) {
      $this->setCache($cache_key, []);
      return [];
    }

    $summary_data = (object) [
      'clusters' => array_map(function ($cluster) {
        return (object) [
          // Id is not set for "Not specified clusters".
          'id' => $cluster->id ?? NULL,
          'name' => $cluster->name,
          'total_funding' => $cluster->totalFunding,
        ];
      }, $clusters),
      'totals' => (object) [
        'sum' => $totals?->objectsSum ?? 0,
        'overlap' => $totals?->overlapCorrection ?? 0,
        'shared' => $totals?->sharedFunding ?? 0,
        'total_funding' => $totals?->totalFunding ?? 0,
      ],
    ];
    $this->setCache($cache_key, $summary_data);
    return $summary_data;
  }

  /**
   * Get a property from a cluster object.
   *
   * @param object $cluster
   *   The cluster for which to retrieve the property.
   * @param string $property
   *   The property to retrieve. See self::getData().
   * @param mixed $default
   *   A default value to return if the property is not set.
   *
   * @return mixed
   *   The value for the property on the cluster, or the default value.
   */
  private function getClusterProperty($cluster, $property, $default = NULL) {
    if (!$cluster || !is_object($cluster)) {
      return $default;
    }
    return property_exists($cluster, $property) ? $cluster->$property : $default;
  }

  /**
   * Get a property from one of the clusters.
   *
   * @param int $cluster_id
   *   The cluster id for which to retrieve the property.
   * @param string $property
   *   The property to retrieve. See self::getData().
   * @param mixed $default
   *   A default value to return if the property is not set.
   *
   * @return mixed
   *   The value for tha property on the cluster, or the default value.
   */
  public function getClusterPropertyById($cluster_id, $property, $default = NULL) {
    $data = $this->getClusterSummaryData();
    if (empty($data) || empty($data->clusters)) {
      return $default;
    }
    $cluster = ArrayHelper::findFirstItemByProperties($data->clusters, ['id' => $cluster_id]);
    return $this->getClusterProperty($cluster, $property, $default);
  }

  /**
   * Get the total funding for a cluster.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param int $default
   *   Optional default value.
   *
   * @return float
   *   The total funding for the given cluster id.
   */
  public function getClusterTotalFunding($cluster_id, $default = 0): float {
    return (float) $this->getClusterPropertyById($cluster_id, 'total_funding', $default);
  }

  /**
   * Get the funding gap for a cluster and the given requirements.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param float $requirements
   *   The requirements to compare the funding against.
   *
   * @return float
   *   The funding gap for the given cluster id.
   */
  public function getClusterFundingGap(int $cluster_id, float $requirements): float {
    $funding = $this->getClusterTotalFunding($cluster_id);
    return $requirements - $funding;
  }

  /**
   * Get the funding coverage for a cluster and the given requirements.
   *
   * @param int $cluster_id
   *   The cluster id.
   * @param float $requirements
   *   The requirements to compare the funding against.
   *
   * @return float
   *   The funding coverage for the given cluster id.
   */
  public function getClusterFundingCoverage($cluster_id, ?float $requirements = 0): float {
    $funding = $this->getClusterTotalFunding($cluster_id);
    return (float) CommonHelper::calculateRatio($funding, $requirements, 4) * 100;
  }

  /**
   * Get the not specified cluster from the result set.
   *
   * This should be the only one with a missing or empty id property.
   *
   * @return object|null
   *   The cluster object or NULL if none can be found.
   */
  public function getNotSpecifiedCluster() {
    $data = $this->getClusterSummaryData();
    if (empty($data) || empty($data->clusters)) {
      return NULL;
    }
    foreach ($data->clusters as $cluster) {
      if (!property_exists($cluster, 'id') || empty($cluster->id)) {
        return $cluster;
      }
    }
    return NULL;
  }

  /**
   * Checks if the current result set has shared funding.
   *
   * @return bool
   *   TRUE if the data contains shared funding, FALSE if it doesn't.
   */
  public function hasSharedClusterFunding() {
    $data = $this->getClusterSummaryData();
    return !empty($data->totals->shared);
  }

  /**
   * Checks if the current result set has shared funding.
   *
   * @return int
   *   The shared funding amount.
   */
  public function getSharedClusterFunding() {
    $data = $this->getClusterSummaryData();
    return !empty($data->totals->shared) ? $data->totals->shared : 0;
  }

  /**
   * Extract cluster ids from the summary data.
   *
   * @return int[]
   *   An array of cluster ids.
   */
  public function getClusterIds() {
    $data = $this->getClusterSummaryData();
    return array_filter(array_map(fn ($cluster) => (int) $cluster->id ?? NULL, $data->clusters ?? []));
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

}
