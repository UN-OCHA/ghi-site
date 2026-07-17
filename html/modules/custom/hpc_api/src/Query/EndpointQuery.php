<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\hpc_api\ConfigService;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;
use JsonMachine\Items;
use Psr\Http\Message\ResponseInterface;

/**
 * Class representing an endpoint query.
 *
 * Includes data retrieval and error handling.
 */
class EndpointQuery {

  use DependencySerializationTrait;
  use SimpleCacheTrait;

  const AUTH_METHOD_NONE = 'none';
  const AUTH_METHOD_BASIC = 'basic_auth';
  const AUTH_METHOD_API_KEY = 'api_key';

  const LOG_ID = 'HPC API';

  private const DEFAULT_CONNECT_TIMEOUT = 3;
  private const DEFAULT_TIMEOUT = 25;
  private const DEFAULT_FLOW_CUSTOM_SEARCH_TIMEOUT = 6;

  /**
   * The config service.
   *
   * @var \Drupal\hpc_api\ConfigService
   */
  protected $configService;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * Flag to inidicate if a cache should be used or not. Defaults to TRUE.
   *
   * @var bool
   */
  protected $useCache;

  /**
   * The cache base time as a timestamp.
   *
   * If set, cached data created before this time will not be used. This is
   * useful in batch contexts.
   *
   * @var int
   */
  protected $cacheBaseTime;

  /**
   * The cache kill switch service.
   *
   * @var \Drupal\Core\PageCache\ResponsePolicy\KillSwitch
   */
  protected $killSwitch;

  /**
   * The request client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The persistent remote data cache service.
   *
   * @var \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface|null
   */
  protected ?RemoteDataCacheInterface $remoteDataCache;

  /**
   * The version of the endpoint to be used.
   *
   * @var string
   */
  protected $endpointVersion;

  /**
   * The endpoint URL that this class queries.
   *
   * @var string
   */
  protected $endpointUrl;

  /**
   * Additional query arguments used for the query.
   *
   * @var array
   */
  protected $endpointArgs = [];

  /**
   * The authentication method to be used.
   *
   * @var string
   */
  protected $authMethod;

  /**
   * An auth header value.
   *
   * @var string
   */
  protected $authHeader;

  /**
   * An array of placeholder substitutions.
   *
   * @var array
   */
  protected $placeholders = [];

  /**
   * Order key if any.
   *
   * @var string
   */
  protected $orderBy;

  /**
   * Sort direction if any.
   *
   * @var int
   */
  protected $sort;

  /**
   * Method for sorting if any.
   *
   * @var int
   */
  protected $sortMethod;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $user;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a new EndpointQuery object.
   */
  public function __construct(ConfigService $config_service, LoggerChannelFactoryInterface $logger_factory, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user, TimeInterface $time, ?RemoteDataCacheInterface $remote_data_cache = NULL) {
    $this->configService = $config_service;
    $this->loggerFactory = $logger_factory;
    $this->killSwitch = $kill_switch;
    $this->httpClient = $http_client;
    $this->user = $user;
    $this->time = $time;
    $this->remoteDataCache = $remote_data_cache;

    $this->endpointVersion = $this->configService->getDefaultApiVersion();
    $this->endpointUrl = NULL;
    $this->endpointArgs = [];
    $this->useCache = TRUE;
    $this->cacheBaseTime = NULL;
    $this->orderBy = NULL;
    $this->sort = SORT_ASC;
    $this->sortMethod = SORT_NUMERIC;
    $this->authMethod = self::AUTH_METHOD_BASIC;
  }

  /**
   * Set the query properties from an arguments array.
   */
  public function setArguments(array $arguments) {
    // As this class is used as a service, we have to make sure to set each
    // property explicitely. Otherwhise we risk to keep some cached values in
    // there that create difficult to debug race conditions.
    $this->endpointVersion = !empty($arguments['api_version']) ? $arguments['api_version'] : $this->configService->getDefaultApiVersion();
    $this->endpointUrl = !empty($arguments['endpoint']) ? $arguments['endpoint'] : NULL;
    if ($this->user->isAuthenticated() && !empty($arguments['endpoint_restricted'])) {
      $this->endpointUrl = $arguments['endpoint_restricted'];
    }
    $this->endpointArgs = !empty($arguments['query_args']) ? $arguments['query_args'] : [];
    $this->orderBy = !empty($arguments['order_by']) ? $arguments['order_by'] : NULL;
    $this->sort = !empty($arguments['sort']) ? $arguments['sort'] : SORT_ASC;
    $this->sortMethod = !empty($arguments['sort_method']) ? $arguments['sort_method'] : SORT_NUMERIC;
    $this->setAuthMethod(!empty($arguments['auth_method']) ? $arguments['auth_method'] : self::AUTH_METHOD_BASIC);
    $this->setUseCache(array_key_exists('cache', $arguments) ? (bool) $arguments['cache'] : $this->useCache());
    $this->setCacheBaseTime(array_key_exists('cache_base_time', $arguments) ? (int) $arguments['cache_base_time'] : 0);
  }

  /**
   * Set if cache should be used.
   *
   * @param bool $status
   *   TRUE if cache should be used (default) or FALSE otherwise.
   */
  public function setUseCache($status = TRUE) {
    $this->useCache = $status;
  }

  /**
   * Check if cache should be used.
   *
   * @return bool
   *   TRUE if cache should be used (default) or FALSE otherwise.
   */
  public function useCache() {
    return $this->useCache ?? TRUE;
  }

  /**
   * Set the cache base time.
   *
   * @param int $timestamp
   *   The base timestamp for using the cache.
   */
  public function setCacheBaseTime($timestamp) {
    $this->cacheBaseTime = $timestamp;
  }

  /**
   * Get the cache base time.
   *
   * @return int
   *   The base time for cache entries.
   */
  public function getCacheBaseTime() {
    return $this->cacheBaseTime ?? NULL;
  }

  /**
   * Get the cache tags for this query.
   *
   * @return array
   *   The cache tags for the current query.
   */
  public function getCacheTags() {
    $cache_tags = [];
    foreach ($this->getPlaceholders() as $key => $value) {
      $cache_tags[] = $key . ':' . $value;
    }
    return $cache_tags;
  }

  /**
   * Replace placeholders with values in an endpoint.
   */
  public function substitutePlaceholders($string) {
    if (empty($string)) {
      return $string;
    }
    $placeholders = $this->getPlaceholders();
    if (!empty($placeholders)) {
      // Replace placeholders with actual values.
      foreach ($placeholders as $placeholder => $value) {
        if (!is_string($value) && !is_int($value)) {
          continue;
        }
        $string = str_replace('{' . $placeholder . '}', $value, $string);
      }
    }
    return $string;
  }

  /**
   * Retrieve the API version used for the query.
   */
  public function getApiVersion() {
    return $this->endpointVersion;
  }

  /**
   * Retrieve the base url for all data API queries.
   *
   * @return string
   *   The base url for the HPC API.
   */
  public function getBaseUrl() {
    $config = $this->configService;
    $url = parse_url($config->get('url'));
    $scheme = $url['scheme'];
    $host = $url['host'];
    $base_url = $scheme . '://' . $host;
    return $base_url;
  }

  /**
   * Set an authentication header for this query.
   */
  public function setAuthHeader($value) {
    $this->authHeader = $value;
  }

  /**
   * Get the authentication headers for this query.
   */
  public function getAuthHeaders() {
    $headers = [];
    $config = $this->configService;
    if ($this->authHeader) {
      $headers['Authorization'] = $this->authHeader;
    }
    elseif ($this->authMethod == self::AUTH_METHOD_BASIC) {
      $username = $config->get('auth_username');
      if ($username) {
        $password = $config->get('auth_password');
        $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
      }
    }
    elseif ($this->authMethod == self::AUTH_METHOD_API_KEY) {
      $api_key = $config->get('api_key');
      if (empty($api_key)) {
        // No backend accessconfigured.
        $this->loggerFactory->get(self::LOG_ID)->error('Missing configuration settings for HPC backend access.');
        return FALSE;
      }
      $headers['Authorization'] = 'Bearer ' . $api_key;
    }
    return $headers;
  }

  /**
   * Get the headers for a request.
   *
   * @return array
   *   An array of headers.
   */
  public function getHeaders() {
    $headers = $this->getAuthHeaders();
    if ($this->configService->get('use_gzip_compression', FALSE)) {
      $headers['Accept-Encoding'] = 'deflate,gzip';
    }
    return $headers;
  }

  /**
   * Execute the current query and preprocess the results.
   *
   * @return object|array|false
   *   The result from the endpoint query or FALSE.
   */
  public function query(): object|array|false {
    $endpoint_url = $this->getFullEndpointUrl();
    $cache_key = $this->getEndpointResponseCacheKey($endpoint_url, 'query');
    $use_remote_cache = $this->canUseRemoteDataCache();
    $remote_cache_cid = $use_remote_cache ? $this->getRemoteDataCacheCid($endpoint_url) : NULL;
    $remote_cache_item = NULL;
    $response_data = NULL;

    // First check if statically cached data is available. Might come from
    // previous requests.
    if ($this->useCache()) {
      if ($use_remote_cache) {
        $remote_cache_item = $this->remoteDataCache->get($remote_cache_cid);
        if ($this->canUseRemoteCacheItem($remote_cache_item)) {
          if ($remote_cache_item->isStale()) {
            $this->remoteDataCache->queueRefresh($remote_cache_item);
          }
          $response_data = $remote_cache_item->getPayload();
        }
      }
      else {
        $response_data = $this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime()) ?: NULL;
      }
    }

    if ($response_data !== NULL) {
      $processed_data = $this->processResponseData($response_data, $cache_key);
      if ($processed_data !== FALSE) {
        return $processed_data;
      }
      $this->cache($cache_key, NULL, TRUE);
      $response_data = NULL;
    }

    // No valid cached data available, so we run the API request.
    $response = $this->sendQuery();
    if (empty($response) || !$response instanceof ResponseInterface) {
      if ($use_remote_cache && $this->remoteDataCache->canServeExpiredOnError() && $this->canUseExpiredRemoteCacheItem($remote_cache_item)) {
        return $this->processResponseData($remote_cache_item->getPayload(), $cache_key);
      }
      return FALSE;
    }
    if ($response->getStatusCode() != 200) {
      $this->handleError($response, $endpoint_url);
      if ($use_remote_cache && $this->remoteDataCache->canServeExpiredOnError() && $this->canUseExpiredRemoteCacheItem($remote_cache_item)) {
        return $this->processResponseData($remote_cache_item->getPayload(), $cache_key);
      }
      return FALSE;
    }

    $response_data = (string) $response->getBody();
    $processed_data = $this->processResponseData($response_data, $cache_key);
    if ($processed_data === FALSE) {
      return FALSE;
    }

    if ($use_remote_cache) {
      $this->remoteDataCache->set($remote_cache_cid, $response_data, [
        'refresher_id' => 'hpc_api_endpoint',
        'endpoint_url' => $endpoint_url,
        'context' => [
          'auth_method' => $this->getAuthMethod(),
        ],
        'cache_tags' => $this->getCacheTags(),
        'fresh_ttl' => (int) $this->configService->get('cache_lifetime'),
      ]);
    }
    else {
      $this->cache($cache_key, $response_data, FALSE, NULL, $this->getCacheTags());
    }
    return $processed_data;
  }

  /**
   * Process the response data.
   *
   * @param string $response
   *   The response data as a string.
   * @param string $cache_key
   *   The cache key.
   *
   * @return array|object|false
   *   The processed data or FALSE.
   */
  private function processResponseData(string $response, string $cache_key) {
    if ($response === '') {
      $this->cache($cache_key, NULL, TRUE);
      return FALSE;
    }

    // Now handle the JSON response, extract the data.
    $json = Items::fromString($response);
    if ($json === NULL) {
      // Malformed JSON or other reason that the decoding has failed. Reset
      // cache to force a new request on following calls.
      $this->cache($cache_key, NULL, TRUE);
    }

    $data = $meta = NULL;
    foreach ($json as $key => $item) {
      switch ($key) {
        case 'data':
          $data = $item;
          break;

        case 'meta':
          $meta = $item;
          break;
      }
    }

    if ($data === NULL) {
      $this->cache($cache_key, NULL, TRUE);
      return FALSE;
    }

    if (is_countable($data) && !count($data)) {
      return [];
    }

    // We support 3 general types of responses:
    // 1. The requested data is directly in the root level of the response data
    //    in form of an array
    // 2. The requested data is inside the objects property in form of an array
    // 2. The requested data is inside the plans property in form of an array.
    $object_list = is_array($data) ? $data : NULL;
    $original_key = NULL;
    if (!$object_list && !empty($data->objects) && is_array($data->objects)) {
      $object_list = $data->objects;
      $original_key = 'objects';
    }
    if (!$object_list && !empty($data->plans) && is_array($data->plans)) {
      $object_list = $data->plans;
      $original_key = 'plans';
    }

    // Apply optional sorting.
    $order_by = $this->orderBy;
    $sort = strtoupper($this->sort);
    $sort_method = $this->sortMethod;

    if ($order_by !== NULL && $object_list && !empty($object_list[0]->$order_by)) {
      uasort($object_list, function ($a, $b) use ($order_by, $sort, $sort_method) {
        if ($sort_method == SORT_NUMERIC) {
          // Sort numeric values.
          if ($sort == SORT_ASC) {
            return $a->$order_by > $b->$order_by;
          }
          if ($sort == SORT_DESC) {
            return $a->$order_by < $b->$order_by;
          }
        }
        else {
          // Sort string values, case insensitive.
          return $sort == SORT_ASC ? strcasecmp($a->$order_by, $b->$order_by) : strcasecmp($b->$order_by, $a->$order_by);
        }
      });
      if ($original_key) {
        $data->$original_key = $object_list;
      }
      else {
        $data = $object_list;
      }
    }

    // Make sure we always have the meta data available.
    if (!empty($meta) && empty($data->meta)) {
      $data->meta = $meta;
    }
    return $data ?? FALSE;
  }

  /**
   * Send an API query to the the given URL.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A http response object on successful request or FALSE in case of a
   *   failure.
   *
   * @see Guzzle
   */
  public function sendQuery() {
    $endpoint_url = $this->getFullEndpointUrl();
    $start = microtime(TRUE);
    try {
      $response = $this->httpClient->get($endpoint_url, $this->getRequestOptions($endpoint_url));
    }
    catch (\Exception $e) {
      $response = method_exists($e, 'getResponse') ? $e->getResponse() : FALSE;
    }

    if (empty($response) || !$response instanceof ResponseInterface || $response->getStatusCode() != 200) {
      // If any of the API requests for the current page fails, prevent Drupal
      // from caching the entire page. That way, panels will be called again on
      // the next request, giving us a chance to fill in the missing
      // information.
      $this->killSwitch->trigger();
    }

    // Keep stats.
    QueryHelper::endpointCallTimeStorage($endpoint_url, microtime(TRUE) - $start);
    return $response;
  }

  /**
   * Fetch a remote endpoint response body without using response cache.
   *
   * @param string $endpoint_url
   *   The fully qualified endpoint URL.
   * @param string|null $auth_method
   *   Optional authentication method override.
   * @param string|null $error
   *   Error storage.
   *
   * @return string|false
   *   The raw response body, or FALSE on failure.
   */
  public function fetchRemoteEndpointResponse(string $endpoint_url, ?string $auth_method = NULL, ?string &$error = NULL): string|false {
    $original_auth_method = $this->getAuthMethod();
    if ($auth_method !== NULL) {
      $this->setAuthMethod($auth_method);
    }

    $start = microtime(TRUE);
    try {
      $response = $this->httpClient->get($endpoint_url, $this->getRequestOptions($endpoint_url));
    }
    catch (\Exception $e) {
      $response = method_exists($e, 'getResponse') ? $e->getResponse() : FALSE;
      $error = $e->getMessage();
    }
    finally {
      $this->setAuthMethod($original_auth_method);
    }

    QueryHelper::endpointCallTimeStorage($endpoint_url, microtime(TRUE) - $start);
    if (empty($response) || !$response instanceof ResponseInterface) {
      return FALSE;
    }
    if ($response->getStatusCode() != 200) {
      $error = trim($response->getReasonPhrase()) ?: 'Endpoint refresh failed with status ' . $response->getStatusCode() . '.';
      $this->handleError($response, $endpoint_url);
      return FALSE;
    }
    return (string) $response->getBody();
  }

  /**
   * Query a pool of endpoints.
   *
   * @param string[] $endpoint_urls
   *   An array of fully qualified endpoint urls.
   */
  public function queryPool($endpoint_urls) {
    $promises = [];
    foreach ($endpoint_urls as $endpoint_url) {
      $cache_key = $this->getEndpointResponseCacheKey($endpoint_url, 'query');
      $use_remote_cache = $this->canUseRemoteDataCache();
      $remote_cache_cid = $use_remote_cache ? $this->getRemoteDataCacheCid($endpoint_url) : NULL;

      // First check if statically cached data is available. Might come from
      // previous requests.
      if ($this->useCache()) {
        if ($use_remote_cache) {
          $remote_cache_item = $this->remoteDataCache->get($remote_cache_cid);
          if ($this->canUseRemoteCacheItem($remote_cache_item)) {
            if ($this->processResponseData($remote_cache_item->getPayload(), $cache_key) !== FALSE) {
              if ($remote_cache_item->isStale()) {
                $this->remoteDataCache->queueRefresh($remote_cache_item);
              }
              continue;
            }
          }
        }
        else {
          $response_data = $this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime());
          if ($response_data !== NULL && $this->processResponseData($response_data, $cache_key) !== FALSE) {
            continue;
          }
        }
      }
      // No cached data available, so we run the API request.
      $start = microtime(TRUE);
      $query_options = $this->getRequestOptions($endpoint_url);
      $promise = $this->httpClient->getAsync($endpoint_url, $query_options);
      $promise->then(
        function ($response) use ($cache_key, $endpoint_url, $remote_cache_cid, $use_remote_cache, $start) {
          QueryHelper::endpointCallTimeStorage($endpoint_url . ' (pooled query)', microtime(TRUE) - $start);

          if (empty($response) || !$response instanceof ResponseInterface) {
            return FALSE;
          }
          if ($response->getStatusCode() != 200) {
            $this->handleError($response, $endpoint_url);
            return FALSE;
          }

          $response_data = (string) $response->getBody();
          $processed_data = $this->processResponseData($response_data, $cache_key);
          if ($processed_data === FALSE) {
            return FALSE;
          }

          if ($use_remote_cache) {
            $this->remoteDataCache->set($remote_cache_cid, $response_data, [
              'refresher_id' => 'hpc_api_endpoint',
              'endpoint_url' => $endpoint_url,
              'context' => [
                'auth_method' => $this->getAuthMethod(),
              ],
              'cache_tags' => $this->getCacheTags(),
              'fresh_ttl' => (int) $this->configService->get('cache_lifetime'),
            ]);
          }
          else {
            $this->cache($cache_key, $response_data, FALSE, NULL, $this->getCacheTags());
          }
        },
      );
      $promises[] = $promise;
    }

    Utils::settle($promises)->wait();
  }

  /**
   * Retrieve data from the API.
   *
   * @return object|array|false
   *   The result from the endpoint query or FALSE.
   */
  public function getData(): object|array|false {
    return $this->query();
  }

  /**
   * Retrieve the endpoint URL used for the query.
   */
  public function getEndpointUrl() {
    return $this->getApiVersion() . '/' . $this->substitutePlaceholders($this->getEndpoint());
  }

  /**
   * Get the full qualified URL for the query.
   *
   * @return string
   *   A string representing the full url, including protocol and query string.
   */
  public function getFullEndpointUrl() {
    $endpoint_url = $this->getBaseUrl() . '/' . $this->getEndpointUrl();
    $query = array_map(function ($item) {
      return $this->substitutePlaceholders($item);
    }, $this->getEndpointArguments());
    $url = Url::fromUri($endpoint_url, ['query' => $query])->toUriString();
    return $url;
  }

  /**
   * Set the endpoint used for the query.
   */
  public function setEndpoint($endpoint) {
    $this->endpointUrl = $endpoint;
  }

  /**
   * Get the endpoint used for the query.
   */
  public function getEndpoint() {
    return $this->endpointUrl;
  }

  /**
   * Set a specific argument.
   */
  public function setEndpointArgument($key, $value) {
    $this->endpointArgs[$key] = $value;
  }

  /**
   * Get a specific argument.
   */
  public function getEndpointArgument($key) {
    return array_key_exists($key, $this->endpointArgs) ? $this->endpointArgs[$key] : NULL;
  }

  /**
   * Set additional arguments used for the query.
   */
  public function setEndpointArguments($endpoint_arguments) {
    $this->endpointArgs = $endpoint_arguments + $this->endpointArgs;
  }

  /**
   * Retrieve additional arguments used for the query.
   */
  public function getEndpointArguments() {
    return $this->endpointArgs;
  }

  /**
   * Set the endpoint version used for the query.
   */
  public function setEndpointVersion($endpoint_version) {
    $this->endpointVersion = $endpoint_version;
  }

  /**
   * Get the endpoint version used for the query.
   */
  public function getEndpointVersion() {
    return $this->endpointVersion;
  }

  /**
   * Set the auth method used for the query.
   */
  public function setAuthMethod($auth_method) {
    $allowed_methods = [
      self::AUTH_METHOD_NONE,
      self::AUTH_METHOD_BASIC,
      self::AUTH_METHOD_API_KEY,
    ];
    if (!in_array($auth_method, $allowed_methods)) {
      return FALSE;
    }
    $this->authMethod = $auth_method;
    return TRUE;
  }

  /**
   * Get the auth method used for the query.
   */
  public function getAuthMethod() {
    return $this->authMethod;
  }

  /**
   * Build the normal response cache key for an endpoint URL.
   *
   * @param string $endpoint_url
   *   The full endpoint URL.
   * @param string|null $called_method
   *   Optional caller method for compatibility with pooled query keys.
   *
   * @return string
   *   The cache key.
   */
  private function getEndpointResponseCacheKey(string $endpoint_url, ?string $called_method = NULL): string {
    return $this->getCacheKey([
      'endpoint' => $endpoint_url,
      'auth_method' => $this->getAuthMethod(),
      'headers' => $this->getAuthHeaders(),
    ], NULL, $called_method);
  }

  /**
   * Build HTTP request options for an endpoint URL.
   *
   * @param string $endpoint_url
   *   The full endpoint URL.
   *
   * @return array
   *   The request options.
   */
  private function getRequestOptions(string $endpoint_url): array {
    return [
      'headers' => $this->getHeaders(),
      'connect_timeout' => $this->getPositiveConfigValue('connect_timeout', self::DEFAULT_CONNECT_TIMEOUT),
      'timeout' => $this->getEndpointTimeout($endpoint_url),
      // @todo Check if we are the only ones who need this.
      'chunk_size_read' => 32768,
    ];
  }

  /**
   * Get the total timeout for an endpoint URL.
   *
   * @param string $endpoint_url
   *   The full endpoint URL.
   *
   * @return int|float
   *   The timeout in seconds.
   */
  private function getEndpointTimeout(string $endpoint_url): int|float {
    $path = parse_url($endpoint_url, PHP_URL_PATH) ?: '';
    if (str_ends_with($path, '/fts/flow/custom-search')) {
      return $this->getPositiveConfigValue('flow_custom_search_timeout', self::DEFAULT_FLOW_CUSTOM_SEARCH_TIMEOUT);
    }
    return $this->getPositiveConfigValue('timeout', self::DEFAULT_TIMEOUT);
  }

  /**
   * Get a positive numeric config value.
   *
   * @param string $key
   *   The config key.
   * @param int|float $default
   *   The default value.
   *
   * @return int|float
   *   The config value or default.
   */
  private function getPositiveConfigValue(string $key, int|float $default): int|float {
    $value = $this->configService->get($key, $default);
    return is_numeric($value) && $value > 0 ? $value + 0 : $default;
  }

  /**
   * Check if the persistent remote data cache can be used.
   *
   * @return bool
   *   TRUE if the remote data cache can be used, FALSE otherwise.
   */
  private function canUseRemoteDataCache(): bool {
    return !$this->authHeader && ($this->remoteDataCache?->isEnabled() ?? FALSE);
  }

  /**
   * Check if a remote cache item can satisfy this request.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem|null $item
   *   The remote cache item.
   *
   * @return bool
   *   TRUE if the item can be used, FALSE otherwise.
   */
  private function canUseRemoteCacheItem(?RemoteDataCacheItem $item): bool {
    if (!$item) {
      return FALSE;
    }
    $cache_base_time = $this->getCacheBaseTime();
    if ($cache_base_time && $item->getFetched() < $cache_base_time) {
      return FALSE;
    }
    return $cache_base_time ? $item->isFresh() : ($item->isFresh() || $item->isStale());
  }

  /**
   * Check if an expired remote cache item can be used after a fetch error.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem|null $item
   *   The remote cache item.
   *
   * @return bool
   *   TRUE if the expired item can be used after an error, FALSE otherwise.
   */
  private function canUseExpiredRemoteCacheItem(?RemoteDataCacheItem $item): bool {
    return $item instanceof RemoteDataCacheItem && $item->isExpired() && !$this->getCacheBaseTime();
  }

  /**
   * Build a persistent remote data cache id for an endpoint URL.
   *
   * @param string $endpoint_url
   *   The full endpoint URL.
   *
   * @return string
   *   The cache id.
   */
  private function getRemoteDataCacheCid(string $endpoint_url): string {
    return $this->remoteDataCache->buildCid('hpc_api_endpoint', $this->getAuthMethod() . "\n" . $this->getAuthHeaderFingerprint() . "\n" . $endpoint_url);
  }

  /**
   * Build a non-secret fingerprint for the resolved authentication headers.
   *
   * @return string
   *   The auth header fingerprint.
   */
  private function getAuthHeaderFingerprint(): string {
    return hash('sha256', serialize($this->getAuthHeaders()));
  }

  /**
   * Set the sort options used for the query.
   */
  public function setSort($order_by, $sort = NULL, $sort_method = NULL) {
    $this->orderBy = $order_by;
    $this->sort = $sort;
    $this->sortMethod = $sort_method;
  }

  /**
   * Set a single placeholder to be used to create the final endpoint url.
   */
  public function setPlaceholder($key, $value) {
    $this->placeholders[$key] = $value;
  }

  /**
   * Set the placeholders to be used to create the final endpoint url.
   */
  public function setPlaceholders($placeholders) {
    $this->placeholders = $placeholders + $this->placeholders;
  }

  /**
   * Retrieve a specific placeholder value.
   */
  public function getPlaceholder($key) {
    $placeholders = $this->getPlaceholders();
    return $placeholders[$key] ?? NULL;
  }

  /**
   * Retrieve an array for placeholder substitution.
   */
  public function getPlaceholders() {
    return $this->placeholders ?? [];
  }

  /**
   * Handle API errors.
   *
   * @param object $response
   *   A http response object, see Guzzle.
   * @param string $endpoint_url
   *   The endpoint url for the failed request.
   */
  public function handleError($response, $endpoint_url) {
    if (!$this->configService->logApiErrors()) {
      return;
    }
    if (empty($response->request) || empty($response->data)) {
      $this->loggerFactory->get(self::LOG_ID)->error('API error, Code: @code, Error: @error for request to @uri', [
        '@code' => $response->getStatusCode(),
        '@error' => $response->getReasonPhrase(),
        '@uri' => $endpoint_url,
      ]);
      return FALSE;
    }

    $data = json_decode($response->getBody()->getContent());
    $status = !empty($data->status) ? $data->status : 'unknown';
    $code = !empty($data->code) ? $data->code : 'unknown';
    $message = !empty($data->message) ? $data->message : 'unknown';
    // Necessary until HPC-4510 is fixed.
    // @todo Review later.
    $message = !empty($message->message) ? $message->message : $message;
    $this->loggerFactory->get(self::LOG_ID)->error('API error, Status: @status, Code: @code, Error: @error for request to @uri', [
      '@status' => $status,
      '@code' => $code,
      '@error' => $message,
      '@uri' => $endpoint_url,
    ]);
  }

}
