<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Query class for fetching plan data with a focus on plan entities.
 */
class PlanClusterSummaryQuery extends EndpointQuery {

  /**
   * Constructs a new PlanClusterSummaryQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'plan/{plan_id}/summary/governingEntities';
    $this->endpointVersion = 'v2';
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = parent::getData();
    if (empty($data) || empty($data->objects)) {
      return NULL;
    }
    return (object) [
      'clusters' => array_map(function ($cluster) {
        return (object) [
          'id' => $cluster->id,
          'name' => $cluster->name,
          'current_requirements' => $cluster->totalRequirements,
          'original_requirements' => $cluster->originalRequirements,
          'total_funding' => $cluster->totalFunding,
          'funding_gap' => $cluster->unmetRequirements,
          'funding_coverage' => $cluster->fundingProgress,
        ];
      }, $data->objects),
      'totals' => [
        'sum' => $data->objectsSum,
        'overlap' => $data->overlapCorrection,
        'shared' => $data->sharedFunding,
        'total_funding' => $data->totalFunding,
      ],
    ];
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
  public function getClusterProperty($cluster_id, $property, $default = NULL) {
    $data = $this->getData();
    if (empty($data) || empty($data->clusters)) {
      return $default;
    }
    $cluster = ArrayHelper::findFirstItemByProperties($data->clusters, ['id' => $cluster_id]);
    return property_exists($cluster, $property) ? $cluster->$property : $default;
  }

}
