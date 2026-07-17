<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_common\Helpers\ArrayHelper;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\Utils;
use JsonMachine\Exception\JsonMachineException;
use JsonMachine\Items;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;
use Psr\Http\Message\ResponseInterface;

/**
 * Class representing an Fabric GraphQL client.
 *
 * Includes data retrieval and error handling.
 */
class FabricClient {

  use DependencySerializationTrait;
  use SimpleCacheTrait;

  const LOG_ID = 'FABRIC API';

  private const DEFAULT_CONNECT_TIMEOUT = 2;
  private const DEFAULT_TIMEOUT = 8;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The logger factory service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

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
   * The remote data cache service.
   *
   * @var \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface|null
   */
  protected ?RemoteDataCacheInterface $remoteDataCache;

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
   * Whether the last query was paginated.
   *
   * @var bool
   */
  private bool $paginated = FALSE;

  /**
   * Constructs a new fabric query object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, KillSwitch $kill_switch, ClientInterface $http_client, ?RemoteDataCacheInterface $remote_data_cache = NULL) {
    $this->configFactory = $config_factory;
    $this->loggerFactory = $logger_factory;
    $this->killSwitch = $kill_switch;
    $this->httpClient = $http_client;
    $this->remoteDataCache = $remote_data_cache;

    $this->useCache = TRUE;
    $this->cacheBaseTime = NULL;
  }

  /**
   * Disable caching.
   */
  public function disableCache() {
    $this->useCache = FALSE;
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
    return $cache_tags;
  }

  /**
   * Get the endpoint url for the graphql queries.
   *
   * @return string
   *   A url string.
   */
  public function getEndpointUrl() {
    $config = $this->configFactory->get('fabric_graphql.settings');
    $worspace_id = $config->get('workspace_id');
    $endpoint_id = $config->get('endpoint_id');
    $fabric_host = $config->get('host');
    return "https://{$fabric_host}/v1/workspaces/{$worspace_id}/graphqlapis/{$endpoint_id}/graphql";
  }

  /**
   * Get an access token.
   *
   * @return string|null
   *   An access token or NULL.
   */
  public function getAccessToken(): ?string {
    $cache_key = self::class . '_' . __METHOD__ . '_access_token';
    $access_token = $this->cache($cache_key);
    if ($access_token) {
      return $access_token;
    }
    $config = $this->configFactory->get('fabric_graphql.settings');
    $tenant_id = $config->get('tenant_id');
    $client_id = $config->get('client_id');
    $client_secret = $config->get('client_secret');
    $token_url = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
    $allowedHosts = ['login.microsoftonline.com'];
    $scopes = ["https://api.fabric.microsoft.com/.default"];

    if (!$tenant_id || !$client_id || !$client_secret) {
      return NULL;
    }

    // Get an access token.
    $tokenRequestContext = new ClientCredentialContext(
      $tenant_id,
      $client_id,
      $client_secret
    );
    $authProvider = new PhpLeagueAuthenticationProvider($tokenRequestContext, $scopes, $allowedHosts);

    // Request an app-only token for the target host.
    $access_token = NULL;
    try {
      $access_token = $authProvider->getAccessTokenProvider()->getAuthorizationTokenAsync($token_url)->wait();
    }
    catch (IdentityProviderException $e) {
      // League's IdentityProviderException often contains the provider
      // response body with error details.
      $response = NULL;
      try {
        $response = $e->getResponseBody();
      }
      catch (\Exception $inner) {
        // Just catch it.
      }
      $error = [
        $e->getMessage(),
      ];
      if (is_array($response) || is_object($response)) {
        $error[] = "Response body:\n" . print_r($response, TRUE);
      }
      else {
        $error[] = "Response body: " . var_export($response, TRUE);
      }
      $this->logError("Error acquiring access token: @error", [
        '@error' => implode("\n", $error),
      ]);
    }
    catch (\Exception $e) {
      $this->logError("Error acquiring access token: @message", [
        '@message' => $e->getMessage(),
      ]);
    }

    $this->cache($cache_key, $access_token, FALSE, NULL, [], 300);
    return $access_token;
  }

  /**
   * Execute a query.
   *
   * @param \Drupal\hpc_api\Query\FabricQuery $query
   *   The query to execute.
   * @param string $key_property
   *   The property to use as a key.
   *
   * @return false|array|object
   *   The result from the fabric query or FALSE on failure.
   */
  public function execute(FabricQuery $query, string $key_property = 'Id'): false|array|object {
    if (!$query->isAggregated()) {
      $query->assureKeyProperty($key_property);
    }
    $data = $this->query($query);
    if (!is_object($data)) {
      return FALSE;
    }
    $query_name = $query->getQueryName();
    if ($query->isAggregated()) {
      return $data?->$query_name?->groupBy[0]?->aggregations ?? (object) [];
    }
    $items = $this->getItems($data, $query_name, $key_property);
    if ($data->$query_name?->hasNextPage ?? FALSE && !empty($data->$query_name?->endCursor)) {
      $query->setAfter($data->$query_name?->endCursor);
      $items += $this->execute($query, $key_property);
      $this->paginated = TRUE;
    }
    return $items;
  }

  /**
   * Execute a query.
   *
   * @param \Drupal\hpc_api\Query\FabricQuery[] $queries
   *   The queries to execute.
   *
   * @return false|array
   *   The result from the fabric query or FALSE on failure.
   */
  public function executeMultiple(array $queries): false|array {
    $query_strings = array_map(fn ($query) => $query->toString(), $queries);
    $query_names = array_map(fn ($query) => $query->getQueryName(), $queries);

    $data = $this->query(implode(' ', $query_strings));
    return is_object($data) ? array_map(fn ($query_name) => $this->getItems($data, $query_name), array_combine($query_names, $query_names)) : FALSE;
  }

  /**
   * Query a pool of data queries asynchronously.
   *
   * @param array $queries
   *   An array of queries, either a FabricQuery objects or strings.
   */
  public function poolDataQueries($queries) {
    $requests = [];
    foreach ($queries as $query) {
      $query = $this->prepareQuery($query);
      $body = $this->buildRequestBody($query);

      // See if we have a cached version already for this request.
      $cache_key = $this->getCacheKey([
        'url' => $this->getEndpointUrl(),
        'body' => $body,
      ], NULL, 'query');
      $use_remote_cache = $this->canUseRemoteDataCache();
      $remote_cache_cid = $use_remote_cache ? $this->getRemoteDataCacheCid($body) : NULL;
      if ($this->useCache()) {
        if ($use_remote_cache) {
          $remote_cache_item = $this->remoteDataCache->get($remote_cache_cid);
          if ($this->canUseRemoteCacheItem($remote_cache_item)) {
            if ($remote_cache_item->isStale()) {
              $this->remoteDataCache->queueRefresh($remote_cache_item);
            }
            continue;
          }
        }
        elseif ($this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime() ?? NULL)) {
          continue;
        }
      }
      $requests[] = [
        'query' => $query,
        'body' => $body,
        'cache_key' => $cache_key,
        'remote_cache_cid' => $remote_cache_cid,
        'use_remote_cache' => $use_remote_cache,
      ];
    }

    if (empty($requests)) {
      return NULL;
    }

    $access_token = $this->getAccessToken();
    if (!$access_token) {
      $error = 'No access token available for GraphQL request.';
      $this->logError($error);
      return FALSE;
    }

    $promises = [];
    foreach ($requests as $request) {
      $post_args = $this->buildPostArgs($request['body'], $access_token);
      $start = microtime(TRUE);
      $promise = $this->httpClient->postAsync($this->getEndpointUrl(), $post_args);
      $promise->then(
        function ($response) use ($request, $start) {
          QueryHelper::endpointCallTimeStorage(preg_replace('/\s+/', ' ', $request['query'] . ' (pooled query)'), microtime(TRUE) - $start);
          $data = $this->processResponse($response, $request['query']);
          if ($data === FALSE || !$this->useCache()) {
            return;
          }
          if ($request['use_remote_cache']) {
            $this->remoteDataCache->set($request['remote_cache_cid'], $data, [
              'refresher_id' => 'fabric_graphql',
              'endpoint_url' => $this->getEndpointUrl(),
              'request_body' => $request['body'],
              'context' => [
                'query' => $request['query'],
              ],
            ]);
            return;
          }
          $this->cache($request['cache_key'], $data, FALSE, NULL, $this->getCacheTags());
        },
      );
      $promises[] = $promise;
    }

    if ($promises) {
      Utils::settle($promises)->wait();
    }
  }

  /**
   * Execute the current query and preprocess the results.
   *
   * @param string|\Drupal\hpc_api\Query\FabricQuery $query
   *   The payload to send to the fabric.
   * @param string $error
   *   Optional: Error storage.
   *
   * @return object|array|false
   *   The result from the fabric query or FALSE on failure.
   */
  public function query(string|FabricQuery $query, ?string &$error = NULL) {
    $query = $this->prepareQuery($query);
    $body = $this->buildRequestBody($query);

    // See if we have a cached version already for this request.
    $cache_key = $this->getCacheKey([
      'url' => $this->getEndpointUrl(),
      'body' => $body,
    ]);
    $use_remote_cache = $this->canUseRemoteDataCache();
    $remote_cache_cid = $use_remote_cache ? $this->getRemoteDataCacheCid($body) : NULL;
    $remote_cache_item = NULL;
    if ($this->useCache()) {
      if ($use_remote_cache) {
        $remote_cache_item = $this->remoteDataCache->get($remote_cache_cid);
        if ($this->canUseRemoteCacheItem($remote_cache_item)) {
          if ($remote_cache_item->isStale()) {
            $this->remoteDataCache->queueRefresh($remote_cache_item);
          }
          return $remote_cache_item->getPayload();
        }
      }
      elseif ($data = $this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime() ?? NULL)) {
        // If we have a cached version, use that.
        return $data;
      }
    }

    $data = $this->fetchRemoteGraphQlRequest($body, $query, $error);
    if ($data !== FALSE) {
      if ($use_remote_cache && $this->useCache()) {
        $this->remoteDataCache->set($remote_cache_cid, $data, [
          'refresher_id' => 'fabric_graphql',
          'endpoint_url' => $this->getEndpointUrl(),
          'request_body' => $body,
          'context' => [
            'query' => $query,
          ],
        ]);
      }
      elseif ($this->useCache() && !$this->paginated) {
        $this->cache($cache_key, $data, FALSE, NULL, $this->getCacheTags());
      }
      return $data;
    }

    if ($use_remote_cache && $this->remoteDataCache->canServeExpiredOnError() && $this->canUseExpiredRemoteCacheItem($remote_cache_item)) {
      return $remote_cache_item->getPayload();
    }
    return FALSE;
  }

  /**
   * Fetch a prepared GraphQL request from Fabric without using response cache.
   *
   * @param string $body
   *   The encoded GraphQL request body.
   * @param string|null $query
   *   The normalized query string for logging.
   * @param string|null $error
   *   Error storage.
   * @param string|null $endpoint_url
   *   Optional endpoint URL override.
   *
   * @return object|false
   *   The decoded Fabric data object, or FALSE on failure.
   */
  public function fetchRemoteGraphQlRequest(string $body, ?string $query = NULL, ?string &$error = NULL, ?string $endpoint_url = NULL): object|false {
    $access_token = $this->getAccessToken();
    if (!$access_token) {
      $error = 'No access token available for GraphQL request.';
      $this->logError($error);
      return FALSE;
    }

    $query = $query ?? $this->extractQueryFromRequestBody($body) ?? $body;
    $endpoint_url = $endpoint_url ?? $this->getEndpointUrl();
    $post_args = $this->buildPostArgs($body, $access_token);

    try {
      $start = microtime(TRUE);
      $response = $this->httpClient->post($endpoint_url, $post_args);
      QueryHelper::endpointCallTimeStorage(preg_replace('/\s+/', ' ', $query), microtime(TRUE) - $start);
    }
    catch (\Exception $e) {
      $this->logError("GraphQL request error for query @query: @message", [
        '@query' => $query,
        '@message' => $e->getMessage(),
      ], $error);
      return FALSE;
    }

    return $this->processResponse($response, $query, $error);
  }

  /**
   * Check if the persistent remote data cache can be used.
   *
   * @return bool
   *   TRUE if the remote data cache can be used, FALSE otherwise.
   */
  private function canUseRemoteDataCache(): bool {
    return $this->remoteDataCache?->isEnabled() ?? FALSE;
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
    // cacheBaseTime is used by import/batch flows that explicitly require
    // fresh data after a known start time, so do not serve stale entries there.
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
   * Build a persistent remote data cache id for a Fabric request body.
   *
   * @param string $body
   *   The encoded GraphQL request body.
   *
   * @return string
   *   The cache id.
   */
  private function getRemoteDataCacheCid(string $body): string {
    return $this->remoteDataCache->buildCid('fabric_graphql', $this->getEndpointUrl() . "\n" . $body);
  }

  /**
   * Build Fabric request arguments.
   *
   * @param string $body
   *   The encoded GraphQL request body.
   * @param string $access_token
   *   The Fabric access token.
   *
   * @return array
   *   The request arguments.
   */
  private function buildPostArgs(string $body, string $access_token): array {
    $config = $this->configFactory->get('fabric_graphql.settings');
    return [
      'body' => $body,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
      'connect_timeout' => $this->getPositiveConfigValue($config->get('connect_timeout'), self::DEFAULT_CONNECT_TIMEOUT),
      'timeout' => $this->getPositiveConfigValue($config->get('timeout'), self::DEFAULT_TIMEOUT),
    ];
  }

  /**
   * Get a positive numeric config value.
   *
   * @param mixed $value
   *   The configured value.
   * @param int|float $default
   *   The default value.
   *
   * @return int|float
   *   The config value or default.
   */
  private function getPositiveConfigValue(mixed $value, int|float $default): int|float {
    return is_numeric($value) && $value > 0 ? $value + 0 : $default;
  }

  /**
   * Extract a query string from an encoded GraphQL request body.
   *
   * @param string $body
   *   The encoded GraphQL request body.
   *
   * @return string|null
   *   The query string, or NULL if the body cannot be decoded.
   */
  private function extractQueryFromRequestBody(string $body): ?string {
    $decoded = Json::decode($body);
    return is_array($decoded) && isset($decoded['query']) ? (string) $decoded['query'] : NULL;
  }

  /**
   * Prepare a GraphQL query for Fabric.
   *
   * @param string|\Drupal\hpc_api\Query\FabricQuery $query
   *   The query to prepare.
   *
   * @return string
   *   The normalized query string.
   */
  private function prepareQuery(string|FabricQuery $query): string {
    $query = $query instanceof FabricQuery ? $query->toString() : $query;
    $query = trim(str_replace(["\r\n", "\r", "\n"], ' ', trim($query)));
    return str_starts_with($query, 'query {') ? $query : 'query { ' . $query . ' }';
  }

  /**
   * Build the JSON request body for a GraphQL query.
   *
   * @param string $query
   *   The GraphQL query.
   *
   * @return string
   *   The encoded request body.
   */
  private function buildRequestBody(string $query): string {
    return Json::encode(['query' => $query]);
  }

  /**
   * Process the GraphQl HTTP response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   A response object.
   * @param string $query
   *   The query string.
   * @param string|null $error
   *   Error storage.
   *
   * @return object|false
   *   An object holding the result data or FALSE on failure.
   */
  private function processResponse(ResponseInterface $response, string $query, ?string &$error = NULL): false|object {
    if (empty($response) || !$response instanceof ResponseInterface) {
      $this->logError("GraphQL response is empty or invalid for query: @query", [
        '@query' => $query,
      ], $error);
      return FALSE;
    }
    if ($response->getStatusCode() != 200) {
      $this->logError("GraphQL status code @status_code for query: @query", [
        '@status_code' => $response->getStatusCode(),
        '@query' => $query,
      ], $error);
      return FALSE;
    }

    $body = (string) $response->getBody();
    if (empty($body)) {
      $this->logError("GraphQL response is empty for query: @query", [
        '@status_code' => $response->getStatusCode(),
        '@query' => $query,
      ], $error);
      return FALSE;
    }

    // Now handle the JSON response, extract the data.
    $data = NULL;
    try {
      $data = Items::fromString($body, ['pointer' => '/data']);
      if ($data === NULL) {
        // Malformed JSON or other reason that the decoding has failed.
        $this->logError("GraphQL returned malformed JSON for query: @query", [
          '@status_code' => $response->getStatusCode(),
          '@query' => $query,
        ], $error);
        return FALSE;
      }

      // Cast into an object and store in cache.
      $data = (object) iterator_to_array($data);
    }
    catch (JsonMachineException $e) {
      $error = $body;
      $this->logError("Failed to parse GraphQL response for query @query with error message @message. Full data received was: <pre>@data</pre>", [
        '@query' => $query,
        '@message' => $e->getMessage(),
        '@data' => print_r($body, TRUE),
      ]);
      return FALSE;
    }

    // @todo Support sorting?
    return $data;
  }

  /**
   * Create a new query.
   *
   * @param string $query_name
   *   The query name.
   * @param array|string $items
   *   The items to fetch.
   * @param array|null $filters
   *   The filters to apply.
   * @param int|null $limit
   *   The limit.
   *
   * @return \Drupal\hpc_api\Query\FabricQuery
   *   Returns a client instance for chaining.
   */
  public function createQuery(string $query_name, mixed $items = NULL, ?array $filters = NULL, ?int $limit = NULL): FabricQuery {
    return new FabricQuery($query_name, $items, $filters, $limit);
  }

  /**
   * Get namespaced items from the given fabric data object.
   *
   * @param object $data
   *   A fabric graphql result object.
   * @param string $namespace
   *   The result namespace.
   * @param string $key_property
   *   The property to use as a key.
   *
   * @return array
   *   The item list.
   */
  public function getItems(object $data, ?string $namespace = NULL, string $key_property = 'Id'): array {
    if ($namespace === NULL) {
      $properties = array_keys(get_object_vars($data));
      $namespace = count($properties) == 1 ? reset($properties) : NULL;
    }
    if (!$namespace || !property_exists($data, $namespace) || !is_object($data->{$namespace} ?? NULL)) {
      return [];
    }
    if (property_exists($data->{$namespace}, 'items')) {
      return ArrayHelper::keyByProperty($data?->{$namespace}?->items ?? [], $key_property);
    }
    if (property_exists($data->{$namespace}, 'groupBy')) {
      $aggregations = array_map(fn ($item) => (object) $item->aggregations, $data?->{$namespace}?->groupBy ?? []);
      return !empty($aggregations) ? (array) reset($aggregations) : [];
    }
    return [];
  }

  /**
   * Log an error.
   *
   * @param string|\Stringable $message
   *   The message to log.
   * @param array $context
   *   Optional: Additional context information.
   * @param string $error
   *   Optional: Error storage.
   */
  private function logError(string|\Stringable $message, array $context = [], ?string &$error = NULL): void {
    $error = (string) (new FormattableMarkup($message, $context));
    $this->loggerFactory->get(self::LOG_ID)->error($message, $context);
  }

}
