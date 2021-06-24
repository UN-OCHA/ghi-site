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
 * Query class for fetching funding data for a plan.
 */
class PlanFundingSummaryQuery extends EndpointQuery {

  /**
   * Constructs a new PlanFundingSummaryQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'fts/flow/plan/summary/{plan_id}';
    $this->endpointVersion = 'v1';
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    $data = (array) parent::getData();
    return [
      'total_funding' => $data['total_funding'],
      'outside_funding' => $data['overall_funding'] - $data['total_funding'],
      'funding_coverage' => $data['funding_progress'],
      'funding_gap' => array_key_exists('unmet_requirements', $data) ? $data['unmet_requirements'] : $data['total_requirements'] - $data['total_funding'],
      'original_requirements' => $data['original_requirements'],
      'current_requirements' => $data['total_requirements'],
    ];
  }

  /**
   * Get a specific property from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property or a default value.
   */
  public function get($property, $default) {
    $data = $this->getData();
    return !empty($data[$property]) ? $data[$property] : $default;
  }

}
