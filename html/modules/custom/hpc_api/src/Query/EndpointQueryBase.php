<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
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
  use DependencySerializationTrait;

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
   * The cache tags.
   *
   * @var string[]
   */
  protected $cacheTags = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQuery $endpoint_query, AccountProxyInterface $user, HidUserData $hid_user_data, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query);

    $this->endpointQuery = clone $endpoint_query;
    $this->user = $user;
    $this->hidUserData = $hid_user_data;
    $this->cache = $cache;
    $this->cacheTags = [];

    $endpoint_public = $plugin_definition['endpoint']['public'] ?? NULL;
    $endpoint_authenticated = $plugin_definition['endpoint']['authenticated'] ?? NULL;
    $endpoint_api_key = $plugin_definition['endpoint']['api_key'] ?? NULL;
    $endpoint_version = $plugin_definition['endpoint']['version'] ?? 'v2';
    $endpoint_query_args = $plugin_definition['endpoint']['query'] ?? [];

    $this->isAutenticatedEndpoint = $endpoint_authenticated && $this->user->isAuthenticated() && $this->getHidAccessToken();
    $endpoint_url = $this->isAutenticatedEndpoint ? $endpoint_authenticated : $endpoint_public;
    $auth_method = EndpointQuery::AUTH_METHOD_BASIC;
    if ($endpoint_api_key) {
      $auth_method = EndpointQuery::AUTH_METHOD_API_KEY;
      $endpoint_url = $endpoint_api_key;
    }

    $this->endpointQuery->setArguments([
      'api_version' => $endpoint_version,
      'endpoint' => $endpoint_url,
      'query_args' => $endpoint_query_args,
      'auth_method' => $auth_method,
    ]);
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

    $cache_args = [
      'endpoint' => $this->getFullEndpointUrl(),
      'auth_method' => $this->endpointQuery->getAuthMethod(),
    ];

    if ($this->isAutenticatedEndpoint && !$this->endpointQuery->isApiKeyRequest()) {
      $hid_access_token = $this->getHidAccessToken();
      if ($hid_access_token) {
        $this->endpointQuery->setAuthHeader('Bearer ' . $hid_access_token);
        $cache_args['user'] = $this->hidUserData->getId();
      }
    }
    // Cache the result in memory.
    $cache_key = $this->getCacheKey($cache_args);
    if ($data = $this->cache($cache_key)) {
      return $data;
    }
    $data = $this->endpointQuery->getData();
    $this->setCache($cache_key, $data);
    return $data;
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
  public function getPlaceholder($key) {
    return $this->endpointQuery->getPlaceholder($key);
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

  /**
   * Set the cache tags for this query.
   *
   * @param array $cache_tags
   *   The cache tags for the current query.
   */
  public function setCacheTags($cache_tags = []) {
    $this->cacheTags = Cache::mergeTags($this->cacheTags, $cache_tags);
  }

  /**
   * Get the cache tags for this query.
   *
   * @return array
   *   The cache tags for the current query.
   */
  public function getCacheTags() {
    $cache_tags = $this->cacheTags;
    $placeholders = $this->getPlaceholders() ?? [];
    foreach ($placeholders as $key => $value) {
      Cache::mergeTags($cache_tags, [$key . ':' . $value]);
    }
    if (array_key_exists('plan_id', $placeholders)) {
      Cache::mergeTags($cache_tags, ['plan_data']);
    }
    return $cache_tags;
  }

  /**
   * Get cached data for the given cache key.
   *
   * @param string $cache_key
   *   The cache key.
   *
   * @return mixed
   *   The cached data if available.
   */
  public function getCache($cache_key) {
    return $this->cache($cache_key);
  }

  /**
   * Set the cache for the given cache id.
   *
   * This will also automatically set the cache tags for the current query. The
   * base implementation of this class just takes the placeholders and
   * transforms them into cache tags.
   *
   * @param string $cache_key
   *   The cache key.
   * @param mixed $data
   *   The data to store for the cache key.
   */
  public function setCache($cache_key, $data) {
    $this->cache($cache_key, $data, FALSE, NULL, $this->getCacheTags());
  }

}
