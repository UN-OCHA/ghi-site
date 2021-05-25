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
class PlanProjectSearchQuery extends EndpointQuery {

  /**
   * Constructs a new PlanProjectSearchQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/project/search';
    $this->endpointVersion = 'v2';
    $this->endpointArgs = [
      'planIds' => '{plan_id}',
      'latest' => 'true',
      'excludeFields' => 'plans,workflowStatusOptions,locations',
      'includeFields' => 'locationIds,planEntityIds',
      'limit' => 1000,
    ];
  }

}
