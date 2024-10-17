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
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class representing an endpoint query.
 *
 * Includes data retrieval and error handling.
 */
class EndpointQuery {

  use DependencySerializationTrait;
  use SimpleCacheTrait;

  const SORT_ASC = 'ASC';
  const SORT_DESC = 'DESC';

  const SORT_METHOD_NUMERIC = 'numeric';
  const SORT_METHOD_STRING = 'string';

  const AUTH_METHOD_NONE = 'none';
  const AUTH_METHOD_BASIC = 'basic_auth';
  const AUTH_METHOD_API_KEY = 'api_key';

  const LOG_ID = 'HPC API';

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
  public function __construct(ConfigService $config_service, LoggerChannelFactoryInterface $logger_factory, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user, TimeInterface $time) {
    $this->configService = $config_service;
    $this->loggerFactory = $logger_factory;
    $this->killSwitch = $kill_switch;
    $this->httpClient = $http_client;
    $this->user = $user;
    $this->time = $time;

    $this->endpointVersion = $this->configService->getDefaultApiVersion();
    $this->endpointUrl = NULL;
    $this->endpointArgs = [];
    $this->useCache = TRUE;
    $this->cacheBaseTime = NULL;
    $this->orderBy = NULL;
    $this->sort = self::SORT_ASC;
    $this->sortMethod = self::SORT_METHOD_NUMERIC;
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
    $this->sort = !empty($arguments['sort']) ? $arguments['sort'] : self::SORT_ASC;
    $this->sortMethod = !empty($arguments['sort_method']) ? $arguments['sort_method'] : self::SORT_METHOD_NUMERIC;
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
   * Execute the current query and preprocess the results.
   *
   * @return object|array
   *   The result from the endpoint query.
   */
  public function query() {
    $endpoint_url = $this->getFullEndpointUrl();

    $cache_key = $this->getCacheKey([
      'endpoint' => $endpoint_url,
      'auth_method' => $this->getAuthMethod(),
      'headers' => $this->getAuthHeaders(),
    ]);

    // First check if statically cached data is available. Might come from
    // previous requests.
    if (!$this->useCache() || !($response = $this->cache($cache_key, NULL, FALSE, $this->getCacheBaseTime()))) {
      // No cached data available, so we run the API request.
      $result = $this->sendQuery();
      if (empty($result) || !$result instanceof ResponseInterface) {
        return FALSE;
      }
      if ($result->getStatusCode() != 200) {
        $this->handleError($result, $endpoint_url);
        return FALSE;
      }

      // Store the result in the static cache variable.
      if ($result->getStatusCode() == 200) {
        // Only cache the response, if the call returned successfully.
        $response = (string) $result->getBody();
        $this->cache($cache_key, $response, FALSE, NULL, $this->getCacheTags());
      }
    }

    if (empty($response)) {
      return [];
    }

    // Now handle the JSON response, extract the data.
    $json = json_decode($response);
    if ($json === NULL) {
      // Malformed JSON or other reason that the decoding has failed. Reset
      // cache to force a new request on following calls.
      $this->cache($cache_key, NULL, TRUE);
    }

    // Workaround for HPC-1840, until top level contains 'data' again.
    if (!$json || !isset($json->data) || empty($json->data)) {
      return !isset($json->data) && is_array($json) ? $json : [];
    }
    $data = $json->data;
    if (!is_array($data) && !is_object($data) && !count($data)) {
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
        if ($sort_method == self::SORT_METHOD_NUMERIC) {
          // Sort numeric values.
          if ($sort == self::SORT_ASC) {
            return $a->$order_by > $b->$order_by;
          }
          if ($sort == self::SORT_DESC) {
            return $a->$order_by < $b->$order_by;
          }
        }
        else {
          // Sort string values, case insensitive.
          return $sort == self::SORT_ASC ? strcasecmp($a->$order_by, $b->$order_by) : strcasecmp($b->$order_by, $a->$order_by);
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
    if (!empty($json->meta) && empty($data->meta)) {
      $data->meta = $json->meta;
    }
    return $data;
  }

  /**
   * Send an API query to the the given URL.
   *
   * @param array $headers
   *   An array of headers to send with the request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A http response object on successful request or FALSE in case of a
   *   failure.
   *
   * @see Guzzle
   */
  public function sendQuery(?array $headers = NULL) {
    if ($headers == NULL) {
      $headers = $this->getAuthHeaders();
    }

    if ($this->configService->get('use_gzip_compression', FALSE)) {
      $headers['Accept-Encoding'] = 'deflate,gzip';
    }

    // Mark this as a backend call so it's not being cached as a public query.
    if ($this->authMethod == self::AUTH_METHOD_API_KEY) {
      $this->endpointArgs['hpc_backend'] = 1;
    }

    $start = microtime(TRUE);
    try {
      $response = $this->httpClient->get($this->getFullEndpointUrl(), [
        'headers' => $headers,
        'timeout' => $this->configService->get('timeout', 30),
          // @todo Check if we are the only ones who need this.
        'chunk_size_read' => 32768,
      ]);
    }
    catch (\Exception $e) {
      if (method_exists($e, 'getResponse')) {
        $response = $e->getResponse();
      }
      else {
        $response = FALSE;
      }
    }

    if (empty($response) || !$response instanceof ResponseInterface || $response->getStatusCode() != 200) {
      // If any of the API requests for the current page fails, prevent Drupal
      // from caching the entire page. That way, panels will be called again on
      // the next request, giving us a chance to fill in the missing
      // information.
      $this->killSwitch->trigger();
    }

    // Keep stats.
    QueryHelper::endpointCallTimeStorage($this->getFullEndpointUrl(), microtime(TRUE) - $start);

    return $response;
  }

  /**
   * Retrieve data from the API.
   *
   * @return object|array
   *   The result from the endpoint query.
   */
  public function getData() {
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
   *
   * @codeCoverageIgnore
   */
  public function setEndpoint($endpoint) {
    $this->endpointUrl = $endpoint;
  }

  /**
   * Get the endpoint used for the query.
   *
   * @codeCoverageIgnore
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
   *
   * @codeCoverageIgnore
   */
  public function setEndpointArguments($endpoint_arguments) {
    $this->endpointArgs = $endpoint_arguments + $this->endpointArgs;
  }

  /**
   * Retrieve additional arguments used for the query.
   *
   * @codeCoverageIgnore
   */
  public function getEndpointArguments() {
    return $this->endpointArgs;
  }

  /**
   * Set the endpoint version used for the query.
   *
   * @codeCoverageIgnore
   */
  public function setEndpointVersion($endpoint_version) {
    $this->endpointVersion = $endpoint_version;
  }

  /**
   * Get the endpoint version used for the query.
   *
   * @codeCoverageIgnore
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
   * Set the sort options used for the query.
   *
   * @codeCoverageIgnore
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
