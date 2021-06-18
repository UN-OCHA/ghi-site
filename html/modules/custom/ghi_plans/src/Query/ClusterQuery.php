<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Query class for fetching plan data with a focus on plan entities.
 */
class ClusterQuery extends EndpointQuery {

  /**
   * Constructs a new PlanEntitiesQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/governingEntity';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment/{attachment_id}';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v2';
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = parent::getData();
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
    $this->setEndpointArguments([
      'planId' => $plan_id,
      'scopes' => 'governingEntityVersion',
    ]);
    $clusters = $this->getData();
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
    $this->setEndpointArguments([
      'planId' => $plan_id,
      'scopes' => 'governingEntityVersion',
    ]);
    $clusters = $this->getData();
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
