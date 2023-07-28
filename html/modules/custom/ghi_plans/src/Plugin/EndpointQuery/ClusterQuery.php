<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for clusters.
 *
 * @EndpointQuery(
 *   id = "cluster_query",
 *   label = @Translation("Cluster query"),
 *   endpoint = {
 *     "public" = "public/governingEntity",
 *     "version" = "v2"
 *   }
 * )
 */
class ClusterQuery extends EndpointQueryBase {

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $data = parent::getData($placeholders, $query_args);
    if (empty($data)) {
      return $data;
    }

    // Key by ID.
    $clusters = [];
    foreach ($data as $cluster) {
      $clusters[$cluster->id] = $this->processClusterObject($cluster);
    }
    return $clusters;
  }

  /**
   * Process and simplify the cluster objects returned by the API.
   *
   * @param object $cluster
   *   Cluster object from the API.
   *
   * @return object
   *   Processed cluster object.
   */
  private function processClusterObject($cluster) {
    return (object) [
      'id' => $cluster->id,
      'name' => $cluster->governingEntityVersion->name,
      'tags' => $cluster->governingEntityVersion->tags ? array_map('strtolower', $cluster->governingEntityVersion->tags) : NULL,
    ];
  }

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param int $plan_id
   *   The plan id to query.
   * @param int $cluster_id
   *   The cluster id to get.
   *
   * @return array
   *   An array of cluster ids.
   */
  public function getCluster($plan_id, $cluster_id) {
    $clusters = $this->getData([], [
      'planId' => $plan_id,
      'scopes' => 'governingEntityVersion',
    ]);
    if (empty($clusters) || !array_key_exists($cluster_id, $clusters)) {
      return NULL;
    }
    return $clusters[$cluster_id];
  }

  /**
   * Get tagged clusters for the given plan id.
   *
   * @param int $plan_id
   *   The plan id to query.
   * @param string $cluster_tag
   *   The cluster tag.
   *
   * @return array
   *   An array of cluster objects, keyed by the cluster id.
   */
  public function getTaggedClustersForPlan($plan_id, $cluster_tag) {
    $this->setCacheTags([
      'plan_id:' . $plan_id,
    ]);
    $clusters = $this->getData([], [
      'planId' => $plan_id,
      'scopes' => 'governingEntityVersion',
    ]);
    if (empty($clusters)) {
      return NULL;
    }
    $tagged_clusters = array_filter($clusters, function ($cluster) use ($cluster_tag) {
      if (empty($cluster->tags)) {
        return FALSE;
      }
      return in_array(strtolower($cluster_tag), $cluster->tags);
    });

    // Now key them by their cluster id for easier reference later.
    return $tagged_clusters;
  }

}
