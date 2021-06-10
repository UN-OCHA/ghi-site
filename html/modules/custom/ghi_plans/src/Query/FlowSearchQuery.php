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
class FlowSearchQuery extends EndpointQuery {

  /**
   * Constructs a new PlanEntitiesQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'fts/flow/custom-search';
    // @codingStandardsIgnoreStart
    // @todo Implement this once HID login has been added.
    // if ($this->user->isAuthenticated()) {
    //   $this->endpointUrl = 'attachment/{attachment_id}';
    // }
    // @codingStandardsIgnoreEnd
    $this->endpointVersion = 'v1';
  }

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
    $this->setEndpointArguments($arguments);
    $data = $this->getData();
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
    return !empty($this->getEndpointArgument('groupby'));
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
  public function getClusterIds($data, array $cluster_ids = NULL) {
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

}
