<?php

namespace Drupal\hpc_api\Query;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\hpc_api\Helpers\QueryHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use GuzzleHttp\ClientInterface;
use JsonMachine\Exception\JsonMachineException;
use JsonMachine\Items;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Microsoft\Kiota\Authentication\Oauth\ClientCredentialContext;
use Microsoft\Kiota\Authentication\PhpLeagueAuthenticationProvider;
use Psr\Http\Message\ResponseInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Class representing an Fabric GraphQL client.
 *
 * Includes data retrieval and error handling.
 */
class FabricClient {

  use DependencySerializationTrait;
  use SimpleCacheTrait;

  const LOG_ID = 'FABRIC API';

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The event dispatcher service.
   *
   * @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

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
   * Constructs a new fabric query object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EventDispatcherInterface $event_dispatcher, LoggerChannelFactoryInterface $logger_factory, KillSwitch $kill_switch, ClientInterface $http_client) {
    $this->configFactory = $config_factory;
    $this->eventDispatcher = $event_dispatcher;
    $this->loggerFactory = $logger_factory;
    $this->killSwitch = $kill_switch;
    $this->httpClient = $http_client;

    $this->useCache = TRUE;
    $this->cacheBaseTime = NULL;
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
   *
   * @return false|object|array
   *   The result from the fabric query or FALSE on failure.
   */
  public function execute(FabricQuery $query): false|object|array {
    $data = $this->query($query);
    return is_object($data) ? $this->getItems($data, $query->getQueryName()) : FALSE;
  }

  /**
   * Execute a query.
   *
   * @param \Drupal\hpc_api\Query\FabricQuery[] $queries
   *   The queries to execute.
   *
   * @return false|object|array
   *   The result from the fabric query or FALSE on failure.
   */
  public function executeMultiple(array $queries): false|object|array {
    $query_strings = array_map(fn ($query) => $query->toString(), $queries);
    $data = $this->query(implode(' ', $query_strings));
    $query_names = array_map(fn ($query) => $query->getQueryName(), $queries);
    return is_object($data) ? array_map(fn ($query_name) => $this->getItems($data, $query_name), array_combine($query_names, $query_names)) : FALSE;
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
    $query = $query instanceof FabricQuery ? $query->toString() : $query;
    $query = trim(str_replace("\n", " ", addslashes(trim($query))));
    $query = !str_starts_with($query, 'query {') ? 'query { ' . $query . ' }' : $query;
    $body = '{"query": "' . $query . '"}';

    $access_token = $this->getAccessToken();
    if (!$access_token) {
      $error = 'No access token available for GraphQL request.';
      $this->logError($error);
      return FALSE;
    }

    $post_args = [
      'body' => $body,
      'headers' => [
        'Authorization' => 'Bearer ' . $access_token,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
      ],
    ];

    // See if we have a cached version already for this request.
    $cache_key = $this->getCacheKey([
      'url' => $this->getEndpointUrl(),
      'body' => $post_args['body'],
    ]);
    if ($this->useCache() && $data = $this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime() ?? NULL)) {
      // If we have a cached version, use that.
      return $data;
    }

    // No cached data available, so we run the API request.
    try {
      $start = microtime(TRUE);
      $response = $this->httpClient->post($this->getEndpointUrl(), $post_args);
      QueryHelper::endpointCallTimeStorage(preg_replace('/\s+/', ' ', $query), microtime(TRUE) - $start);
    }
    catch (\Exception $e) {
      $this->logError("GraphQL request error for query @query: @message", [
        '@query' => $query,
        '@message' => $e->getMessage(),
      ], $error);
      return FALSE;
    }

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
        // Reset cache to force a new request on following calls.
        $this->cache($cache_key, NULL, TRUE);
        return FALSE;
      }

      // Cast into an object and store in cache.
      $data = (object) iterator_to_array($data);
      $this->cache($cache_key, $data, FALSE, NULL, $this->getCacheTags());
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
    return $namespace ? ArrayHelper::keyByProperty($data?->{$namespace}?->items ?? [], $key_property) : [];
  }

  /**
   * Log an error.
   *
   * @param string|\Stringable $message
   *   The message to log.
   * @param array $context
   *   Optional: Additional context information.
   * @param array $error
   *   Optional: Error storage.
   */
  private function logError(string|\Stringable $message, array $context = [], &$error = []): void {
    $error[] = (string) (new FormattableMarkup($message, $context));
    $this->loggerFactory->get(self::LOG_ID)->error($message, $context);
  }

}
