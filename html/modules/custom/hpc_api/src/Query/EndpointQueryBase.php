<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_common\Hid\HidUserData;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for endpoint query plugins.
 */
abstract class EndpointQueryBase extends PluginBase implements EndpointQueryPluginInterface, ContainerFactoryPluginInterface {

  use SimpleCacheTrait;

  /**
   * The endpoint query service.
   *
   * @var \Drupal\hpc_api\Query\EndpointQuery
   */
  public $endpointQuery;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * The HID user data service.
   *
   * @var \Drupal\hpc_common\Hid\HidUserData
   */
  protected $hidUserData;

  /**
   * Flag set if an authenticated endpoint is used.
   *
   * @var bool
   */
  protected $isAutenticatedEndpoint;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  public $cache;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQuery $endpoint_query, AccountProxyInterface $user, HidUserData $hid_user_data, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query);

    $this->endpointQuery = clone $endpoint_query;
    $this->user = $user;
    $this->hidUserData = $hid_user_data;
    $this->cache = $cache;

    $endpoint_public = $plugin_definition['endpoint']['public'] ?? NULL;
    $endpoint_authenticated = $plugin_definition['endpoint']['authenticated'] ?? NULL;
    $endpoint_api_key = $plugin_definition['endpoint']['api_key'] ?? NULL;
    $endpoint_version = $plugin_definition['endpoint']['version'] ?? 'v2';
    $endpoint_query_args = $plugin_definition['endpoint']['query'] ?? [];

    $this->isAutenticatedEndpoint = $endpoint_authenticated && $this->user->isAuthenticated() && $this->getHidAccessToken();
    if ($endpoint_api_key) {
      $this->endpointQuery->setAuthMethod(EndpointQuery::AUTH_METHOD_API_KEY);
      $this->endpointQuery->setEndpoint($endpoint_api_key);
    }
    else {
      $this->endpointQuery->setAuthMethod(EndpointQuery::AUTH_METHOD_BASIC);
      $this->endpointQuery->setEndpoint($this->isAutenticatedEndpoint ? $endpoint_authenticated : $endpoint_public);
    }

    $this->endpointQuery->setEndpointVersion($endpoint_version);
    $this->endpointQuery->setEndpointArguments($endpoint_query_args);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('hpc_api.endpoint_query'),
      $container->get('current_user'),
      $container->get('hpc_common.hid_user_data'),
      $container->get('cache.data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginDefinition['id'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * Get the HID access token for the user.
   *
   * @return string|null
   *   The HID access token if available.
   */
  private function getHidAccessToken() {
    return $this->hidUserData->getAccessToken($this->user);
  }

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $this->endpointQuery->setPlaceholders($placeholders);
    $this->endpointQuery->setEndpointArguments($query_args);

    if ($this->isAutenticatedEndpoint) {
      $hid_access_token = $this->getHidAccessToken();
      if ($hid_access_token) {
        $this->endpointQuery->setAuthHeader('Bearer ' . $this->hidUserData->getAccessToken($this->user));
      }
    }
    // Cache the result in memory.
    $cache_key = $this->getCacheKey([
      'endpoint' => $this->getFullEndpointUrl(),
      'auth_method' => $this->endpointQuery->getAuthMethod(),
    ]);
    if (!$this->cache($cache_key)) {
      $this->cache($cache_key, $this->endpointQuery->getData());
    }
    return $this->cache($cache_key);
  }

  /**
   * {@inheritdoc}
   */
  public function setPlaceholder($key, $value) {
    $this->endpointQuery->setPlaceholder($key, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholders() {
    return $this->endpointQuery->getPlaceholders();
  }

  /**
   * {@inheritdoc}
   */
  public function getFullEndpointUrl() {
    return $this->endpointQuery->getFullEndpointUrl();
  }

}
