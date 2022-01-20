<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Provides a query plugin for plan cluster summary.
 *
 * @EndpointQuery(
 *   id = "plan_funding_cluster_query",
 *   label = @Translation("Plan funding cluster query"),
 *   endpoint = {
 *     "public" = "plan/{plan_id}/summary/governingEntities",
 *     "version" = "v2"
 *   }
 * )
 */
class PlanClusterSummaryQuery extends EndpointQueryBase {

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $data = parent::getData($placeholders);
    if (empty($data) || empty($data->objects)) {
      return NULL;
    }

    $totals = property_exists($data, 'totals') ? $data->totals : $data;

    return (object) [
      'clusters' => array_map(function ($cluster) {
        return (object) [
          // Id is not set for "Not specified clusters".
          'id' => property_exists($cluster, 'id') ? $cluster->id : NULL,
          'name' => $cluster->name,
          'current_requirements' => property_exists($cluster, 'totalRequirements') ? $cluster->totalRequirements : NULL,
          'original_requirements' => property_exists($cluster, 'originalRequirements') ? $cluster->originalRequirements : NULL,
          'total_funding' => $cluster->totalFunding,
          'funding_gap' => property_exists($cluster, 'unmetRequirements') ? $cluster->unmetRequirements : NULL,
          'funding_coverage' => property_exists($cluster, 'fundingProgress') ? $cluster->fundingProgress : NULL,
        ];
      }, $data->objects),
      'totals' => (object) [
        'sum' => $totals->objectsSum,
        'overlap' => $totals->overlapCorrection,
        'shared' => $totals->sharedFunding,
        'total_funding' => $totals->totalFunding,
      ],
    ];
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
   *   The value for tha property on the cluster, or the default value.
   */
  public function getClusterProperty($cluster, $property, $default = NULL) {
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
    $data = $this->getData();
    if (empty($data) || empty($data->clusters)) {
      return $default;
    }
    $cluster = ArrayHelper::findFirstItemByProperties($data->clusters, ['id' => $cluster_id]);
    return $this->getClusterProperty($cluster, $property, $default);
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
    $data = $this->getData();
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
  public function hasSharedFunding() {
    $data = $this->getData();
    return !empty($data->totals->shared);
  }

  /**
   * Checks if the current result set has shared funding.
   *
   * @return int
   *   The shared funding amount.
   */
  public function getSharedFunding() {
    $data = $this->getData();
    return !empty($data->totals->shared) ? $data->totals->shared : 0;
  }

}
