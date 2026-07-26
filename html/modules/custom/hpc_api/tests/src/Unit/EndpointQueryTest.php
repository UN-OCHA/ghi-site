<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_api\ConfigService;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers Drupal\hpc_api\Query\EndpointQuery
 *
 * @group HPC API
 */
class EndpointQueryTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * The endpoint query instance.
   *
   * @var Drupal\hpc_api\Query\EndpointQuery
   */
  protected $query;

  /**
   * The logger factory.
   *
   * @var Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The logger channel to use.
   *
   * @var Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $loggerChannel;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock config.
    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'url' => 'https://api.hpc.tools',
        'default_api_version' => 'v1',
        'auth_username' => 'authname',
        'auth_password' => 'authpass',
        'api_key' => 'apikey123',
        'public_base_path' => 'public/fts',
        'connect_timeout' => 3,
        'timeout' => 25,
        'flow_custom_search_timeout' => 6,
        'cache_lifetime' => 3600,
        'use_gzip_compression' => FALSE,
      ],
    ]);

    // Get the mock api responses.
    $usage_year_response_body = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $plan_projects_response_body = file_get_contents(__DIR__ . '/Mocks/plan-projects-id-642-year-2018-groupBy-plan.json');
    $error_response_body = file_get_contents(__DIR__ . '/Mocks/error-response.json');

    // Mock httpClient.
    $client = $this->prophesize('GuzzleHttp\Client');

    // When the request method is called on the HTTP client, with "GET", a
    // specific URL of our choosing and any third argument, make sure the
    // following response is sent.
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())->will(function () use ($usage_year_response_body) {
      return new Response(200, [], $usage_year_response_body);
    });
    $client->get('https://api.hpc.tools/v1/fts/project/plan?planid=642&groupBy=plan&year=2018', Argument::any())->will(function () use ($plan_projects_response_body) {
      return new Response(200, [], $plan_projects_response_body);
    });
    $client->get('https://api.hpc.tools/v1/fts/project/plan?planid=642&groupBy=plan48644&year=2018', Argument::any())->will(function () use ($error_response_body) {
      return new Response(400, [], $error_response_body);
    });

    $http_client = $client->reveal();

    // Mock logger.
    $this->loggerFactory = $this->prophesize(LoggerChannelFactoryInterface::class);
    $this->loggerChannel = $this->prophesize(LoggerChannelInterface::class);
    $logger = $this->loggerFactory->reveal();

    // Mock kill switch.
    $kill_switch = $this->prophesize(KillSwitch::class)->reveal();

    $config_service = new ConfigService($config_factory);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('hpc_api.config', $config_service);
    \Drupal::setContainer($container);

    $current_user = $this->prophesize(AccountProxyInterface::class)->reveal();
    $time = $this->prophesize(TimeInterface::class)->reveal();

    $this->query = new OverrideEndpointQuery($config_service, $logger, $kill_switch, $http_client, $current_user, $time);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    parent::tearDown();
    unset($this->query);

    $container = new ContainerBuilder();
    \Drupal::setContainer($container);
  }

  /**
   * Data provider for substitutePlaceholders.
   */
  public function substitutePlaceholdersDataProvider() {
    return [
      [
        'fts/{bundle}/{id}',
        ['bundle' => 'plan', 'id' => '714'],
        'fts/plan/714',
      ],
      [
        'donors/{id}/{display}/{year}',
        ['id' => '2917', 'display' => 'flows', 'year' => '2018'],
        'donors/2917/flows/2018',
      ],
      [
        'fts/plan/{test}',
        ['test' => []],
        'fts/plan/{test}',
      ],
    ];
  }

  /**
   * Check the placeholders are substituted correctly.
   *
   * @group EndpointQuery
   * @dataProvider substitutePlaceholdersDataProvider
   */
  public function testSubstitutePlaceholders($endpoint, $placeholders, $result) {
    // Set placeholders.
    $this->query->setPlaceholders($placeholders);

    $this->assertEquals($result, $this->query->substitutePlaceholders($endpoint));
  }

  /**
   * Data provider for getAuthHeaders.
   */
  public function getAuthHeadersDataProvider() {
    return [
      [EndpointQuery::AUTH_METHOD_BASIC, 'Basic YXV0aG5hbWU6YXV0aHBhc3M='],
      [EndpointQuery::AUTH_METHOD_API_KEY, 'Bearer apikey123'],
    ];
  }

  /**
   * Check the auth headers are set correctly.
   *
   * @group EndpointQuery
   * @dataProvider getAuthHeadersDataProvider
   */
  public function testGetAuthHeaders($auth_method, $authorization_header_value) {
    // Set arguments.
    $this->query->setArguments([
      'auth_method' => $auth_method,
    ]);

    $headers = [];
    $headers['Authorization'] = $authorization_header_value;
    $this->assertEquals($headers, $this->query->getAuthHeaders());
  }

  /**
   * Data provider for getAuthMethod.
   */
  public function getAuthMethodDataProvider() {
    return [
      [EndpointQuery::AUTH_METHOD_NONE, EndpointQuery::AUTH_METHOD_NONE],
      [EndpointQuery::AUTH_METHOD_BASIC, EndpointQuery::AUTH_METHOD_BASIC],
      [EndpointQuery::AUTH_METHOD_API_KEY, EndpointQuery::AUTH_METHOD_API_KEY],
      ['test', EndpointQuery::AUTH_METHOD_BASIC],
    ];
  }

  /**
   * Check the auth method is set correctly.
   *
   * @group EndpointQuery
   * @dataProvider getAuthMethodDataProvider
   */
  public function testGetAuthMethod($auth_method, $result) {
    $this->query->setAuthMethod($auth_method);
    $this->assertEquals($result, $this->query->getAuthMethod());
  }

  /**
   * Check the base url is set correctly.
   *
   * @group EndpointQuery
   */
  public function testGetBaseUrl() {
    $this->assertEquals('https://api.hpc.tools', $this->query->getBaseUrl());
  }

  /**
   * Data provider for getFullEndpointUrl.
   */
  public function getFullEndpointUrlDataProvider() {
    return [
      [
        'fts/flow/usage-years/location/1',
        [],
        'https://api.hpc.tools/v1/fts/flow/usage-years/location/1',
      ],
      [
        'fts/project/plan',
        ['planid' => '642', 'groupBy' => 'plan', 'year' => '2018'],
        'https://api.hpc.tools/v1/fts/project/plan?planid=642&groupBy=plan&year=2018',
      ],
    ];
  }

  /**
   * Check the full endpoint url is set correctly.
   *
   * @group EndpointQuery
   * @dataProvider getFullEndpointUrlDataProvider
   */
  public function testGetFullEndpointUrl($endpoint, $query_args, $result) {
    // Set arguments.
    $this->query->setArguments([
      'endpoint' => $endpoint,
      'query_args' => $query_args,
    ]);

    $this->assertEquals($result, $this->query->getFullEndpointUrl());
  }

  /**
   * Test setting and getting auth headers.
   */
  public function testAuthHeaders() {
    $this->query->setArguments([]);
    $this->assertArrayHasKey('Authorization', $this->query->getAuthHeaders());
    $this->assertStringStartsWith('Basic', $this->query->getAuthHeaders()['Authorization']);
    $this->query->setAuthMethod(EndpointQuery::AUTH_METHOD_API_KEY);
    $this->assertArrayHasKey('Authorization', $this->query->getAuthHeaders());
    $this->assertStringStartsWith('Bearer', $this->query->getAuthHeaders()['Authorization']);
    $this->query->setAuthMethod(EndpointQuery::AUTH_METHOD_NONE);
    $this->assertEquals([], $this->query->getAuthHeaders());
    $this->query->setAuthHeader('123');
    $this->assertEquals(['Authorization' => '123'], $this->query->getAuthHeaders());
  }

  /**
   * Test setting and getting endpoint url.
   */
  public function testEndpointUrl() {
    $this->query->setEndpoint('fts/project/plan');
    $this->assertEquals('fts/project/plan', $this->query->getEndpoint());
    $this->assertEquals('v1/fts/project/plan', $this->query->getEndpointUrl());
  }

  /**
   * Test setting and getting endpoint version.
   */
  public function testEndpointVersion() {
    $this->query->setEndpointVersion('v1');
    $this->assertEquals('v1', $this->query->getEndpointVersion());
    $this->query->setEndpointVersion('v2');
    $this->assertEquals('v2', $this->query->getEndpointVersion());
  }

  /**
   * Test setting and getting endpoint arguments.
   */
  public function testEndpointArgument() {
    $this->query->setArguments([]);
    $this->assertEquals([], $this->query->getEndpointArguments());
    $this->query->setEndpointArguments([]);
    $this->assertEquals([], $this->query->getEndpointArguments());

    $this->query->setArguments([]);
    $this->query->setEndpointArguments(['plan_id' => 1]);
    $this->assertEquals(['plan_id' => 1], $this->query->getEndpointArguments());
    $this->assertEquals(1, $this->query->getEndpointArgument('plan_id'));

    $this->query->setArguments([]);
    $this->query->setEndpointArgument('plan_id', 1);
    $this->assertEquals(['plan_id' => 1], $this->query->getEndpointArguments());
    $this->assertEquals(1, $this->query->getEndpointArgument('plan_id'));
  }

  /**
   * Test setting and getting endpoint placeholders.
   */
  public function testEndpointPlaceholder() {
    $this->query->setArguments([]);
    $this->assertEquals([], $this->query->getPlaceholders());
    $this->query->setPlaceholders([]);
    $this->assertEquals([], $this->query->getPlaceholders());

    $this->query->setArguments([]);
    $this->query->setPlaceholders(['plan_id' => 1]);
    $this->assertEquals(['plan_id' => 1], $this->query->getPlaceholders());
    $this->assertEquals(1, $this->query->getPlaceholder('plan_id'));

    $this->query->setArguments([]);
    $this->query->setPlaceholder('plan_id', 1);
    $this->assertEquals(['plan_id' => 1], $this->query->getPlaceholders());
    $this->assertEquals(1, $this->query->getPlaceholder('plan_id'));
  }

  /**
   * Test getting the data.
   *
   * @group EndpointQuery
   */
  public function testGetData() {
    // Test usage year API.
    $this->assertUsageYearApi();

    // Test projects plan API.
    $this->assertProjectsPlanApi();

    // Test errors.
    $this->assertApiErrors();
  }

  /**
   * Test request timeout options for regular legacy endpoint requests.
   */
  public function testEndpointRequestUsesConfiguredTimeouts(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/plan-projects-id-642-year-2018-groupBy-plan.json');
    $client = $this->mockCapturingHttpClient($payload);
    $query = $this->createEndpointQueryWithClient($client);
    $query->setArguments([
      'endpoint' => 'fts/project/plan',
      'query_args' => [
        'planid' => 642,
        'groupBy' => 'plan',
        'year' => 2018,
      ],
    ]);

    $query->getData();

    $this->assertSame(3, $client->requests[0]['options']['connect_timeout']);
    $this->assertSame(25, $client->requests[0]['options']['timeout']);
  }

  /**
   * Test request timeout options for legacy custom search requests.
   */
  public function testFlowCustomSearchRequestUsesConfiguredTimeout(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $client = $this->mockCapturingHttpClient($payload);
    $query = $this->createEndpointQueryWithClient($client);
    $query->setArguments([
      'endpoint' => 'fts/flow/custom-search',
      'query_args' => [
        'groupBy' => 'cluster',
      ],
    ]);

    $query->getData();

    $this->assertSame(3, $client->requests[0]['options']['connect_timeout']);
    $this->assertSame(6, $client->requests[0]['options']['timeout']);
  }

  /**
   * Test that a fresh remote data cache hit avoids HTTP.
   */
  public function testFreshRemoteDataCacheHitAvoidsHttpRequest(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $item = $this->createRemoteDataCacheItem($payload, 1000, 1200, 1600);
    $remote_cache = $this->mockRemoteDataCache($item);

    $query = $this->createRemoteDataEndpointQuery($this->mockHttpClientThatFailsOnGet(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
  }

  /**
   * Test that stale remote data is returned and refresh is queued.
   */
  public function testStaleRemoteDataCacheHitQueuesRefresh(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $item = $this->createRemoteDataCacheItem($payload, 1000, 900, 1600);
    $remote_cache = $this->mockRemoteDataCache($item);
    $remote_cache->queueRefresh($item)->shouldBeCalledOnce();

    $query = $this->createRemoteDataEndpointQuery($this->mockHttpClientThatFailsOnGet(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
  }

  /**
   * Test that a failed live refresh falls back to cached remote data.
   */
  public function testFailedLiveRefreshFallsBackToRemoteDataCacheItem(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $item = $this->createRemoteDataCacheItem($payload, 1000, 900, 1600, 800);

    $remote_cache = $this->mockRemoteDataCache($item);
    $remote_cache->canServeExpiredOnError()->willReturn(TRUE);

    $client = $this->prophesize('GuzzleHttp\Client');
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())
      ->willReturn(new Response(504, [], 'Gateway Timeout'))
      ->shouldBeCalledOnce();

    $query = $this->createRemoteDataEndpointQuery($client->reveal(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
      'cache_base_time' => 500,
    ]);

    $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
  }

  /**
   * Test that a remote cache miss stores a successful endpoint response.
   */
  public function testRemoteDataCacheMissStoresResponseBody(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $remote_cache = $this->prophesize(RemoteDataCacheInterface::class);
    $remote_cache->isEnabled()->willReturn(TRUE);
    $remote_cache->buildCid('hpc_api_endpoint', Argument::type('string'))->willReturn('hpc_api_endpoint:test');
    $remote_cache->get('hpc_api_endpoint:test')->willReturn(NULL);
    $remote_cache->set('hpc_api_endpoint:test', $payload, Argument::that(function (array $metadata) {
      return $metadata['refresher_id'] === 'hpc_api_endpoint'
        && $metadata['endpoint_url'] === 'https://api.hpc.tools/v1/fts/flow/usage-years/location/1'
        && $metadata['context']['auth_method'] === EndpointQuery::AUTH_METHOD_BASIC
        && $metadata['fresh_ttl'] === 3600;
    }))->shouldBeCalledOnce();

    $client = $this->prophesize('GuzzleHttp\Client');
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())
      ->willReturn(new Response(200, [], $payload));

    $query = $this->createRemoteDataEndpointQuery($client->reveal(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
  }

  /**
   * Test that an invalid remote data cache hit falls back to HTTP.
   */
  public function testInvalidRemoteDataCacheHitFallsBackToHttpRequest(): void {
    $invalid_payload = '{"status":"error","code":"TemporaryError","message":"No data"}';
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $item = $this->createRemoteDataCacheItem($invalid_payload, 1000, 1200, 1600);
    $remote_cache = $this->mockRemoteDataCache($item);
    $remote_cache->set('hpc_api_endpoint:test', $payload, Argument::type('array'))->shouldBeCalledOnce();

    $client = $this->prophesize('GuzzleHttp\Client');
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())
      ->willReturn(new Response(200, [], $payload))
      ->shouldBeCalledOnce();

    $query = $this->createRemoteDataEndpointQuery($client->reveal(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
  }

  /**
   * Test that invalid HTTP 200 responses are not stored permanently.
   */
  public function testRemoteDataCacheMissDoesNotStoreInvalidResponseBody(): void {
    $invalid_payload = '{"status":"error","code":"TemporaryError","message":"No data"}';
    $remote_cache = $this->prophesize(RemoteDataCacheInterface::class);
    $remote_cache->isEnabled()->willReturn(TRUE);
    $remote_cache->buildCid('hpc_api_endpoint', Argument::type('string'))->willReturn('hpc_api_endpoint:test');
    $remote_cache->get('hpc_api_endpoint:test')->willReturn(NULL);
    $remote_cache->set('hpc_api_endpoint:test', $invalid_payload, Argument::type('array'))->shouldNotBeCalled();

    $client = $this->prophesize('GuzzleHttp\Client');
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())
      ->willReturn(new Response(200, [], $invalid_payload));

    $query = $this->createRemoteDataEndpointQuery($client->reveal(), $remote_cache->reveal());
    $query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    $this->assertFalse($query->getData());
  }

  /**
   * Test that persistent endpoint cache ids vary by resolved auth headers.
   */
  public function testRemoteDataCacheCidIncludesAuthHeaderFingerprint(): void {
    $payload = file_get_contents(__DIR__ . '/Mocks/usage-year-location-id-1.json');
    $fingerprints = [];

    $remote_cache = $this->prophesize(RemoteDataCacheInterface::class);
    $remote_cache->isEnabled()->willReturn(TRUE);
    $remote_cache->buildCid('hpc_api_endpoint', Argument::type('string'))->will(function ($args) use (&$fingerprints) {
      $fingerprints[] = $args[1];
      return 'hpc_api_endpoint:' . count($fingerprints);
    });
    $remote_cache->get(Argument::type('string'))->willReturn(NULL);
    $remote_cache->set(Argument::type('string'), $payload, Argument::type('array'))->shouldBeCalledTimes(2);

    $client = $this->prophesize('GuzzleHttp\Client');
    $client->get('https://api.hpc.tools/v1/fts/flow/usage-years/location/1', Argument::any())->will(function () use ($payload) {
      return new Response(200, [], $payload);
    });

    foreach (['authpass-one', 'authpass-two'] as $password) {
      $query = $this->createRemoteDataEndpointQuery($client->reveal(), $remote_cache->reveal(), [
        'auth_password' => $password,
      ]);
      $query->setArguments([
        'endpoint' => 'fts/flow/usage-years/location/1',
      ]);
      $this->assertEquals($this->getUsageYearApiMockResponse(), $query->getData());
    }

    $this->assertCount(2, $fingerprints);
    $this->assertNotSame($fingerprints[0], $fingerprints[1]);
    $this->assertStringContainsString(hash('sha256', serialize([
      'Authorization' => 'Basic ' . base64_encode('authname:authpass-one'),
    ])), $fingerprints[0]);
    $this->assertStringContainsString(hash('sha256', serialize([
      'Authorization' => 'Basic ' . base64_encode('authname:authpass-two'),
    ])), $fingerprints[1]);
    $this->assertStringNotContainsString('authpass-one', $fingerprints[0]);
    $this->assertStringNotContainsString(base64_encode('authname:authpass-one'), $fingerprints[0]);
  }

  /**
   * Test usage year api.
   */
  protected function assertUsageYearApi() {
    // Set arguments.
    $this->query->setArguments([
      'endpoint' => 'fts/flow/usage-years/location/1',
    ]);

    // Asserting the response.
    $this->assertEquals($this->getUsageYearApiMockResponse(), $this->query->getData());
  }

  /**
   * Test projects plan api.
   */
  protected function assertProjectsPlanApi() {
    // Set arguments.
    $this->query->setArguments([
      'endpoint' => 'fts/project/plan',
      'query_args' => [
        'planid' => 642,
        'groupBy' => 'plan',
      ],
    ]);

    // This is just to invoke setEndpointArgument and thereby cover it in code
    // coverage.
    $this->query->setEndpointArgument('year', 2018);

    // Asserting the response.
    $this->assertEquals($this->getProjectPlanApiMockResponse(), $this->query->getData());
  }

  /**
   * Test errors from API.
   */
  protected function assertApiErrors() {
    // Set arguments.
    $this->query->setArguments([
      'endpoint' => 'fts/project/plan',
      'query_args' => [
        'planid' => 642,
        'groupBy' => 'plan48644',
        'year' => 2018,
      ],
    ]);

    // Set the logger response.
    $this->loggerFactory->get(EndpointQuery::LOG_ID)->willReturn($this->loggerChannel->reveal());
    $this->loggerChannel->error('API error, Code: 400, Error: Bad Request');

    // Asserting the response.
    $this->assertEquals(FALSE, $this->query->getData());
  }

  /**
   * Create an endpoint query wired to the remote data cache under test.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface $remote_cache
   *   The remote data cache service.
   * @param array $settings
   *   The hpc_api.settings overrides.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   The endpoint query.
   */
  private function createRemoteDataEndpointQuery(ClientInterface $http_client, RemoteDataCacheInterface $remote_cache, array $settings = []): EndpointQuery {
    $settings += [
      'url' => 'https://api.hpc.tools',
      'default_api_version' => 'v1',
      'auth_username' => 'authname',
      'auth_password' => 'authpass',
      'api_key' => 'apikey123',
      'public_base_path' => 'public/fts',
      'connect_timeout' => 3,
      'timeout' => 25,
      'flow_custom_search_timeout' => 6,
      'cache_lifetime' => 3600,
      'use_gzip_compression' => FALSE,
    ];
    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => $settings,
    ]);
    $config_service = new ConfigService($config_factory);

    $current_user = $this->prophesize(AccountProxyInterface::class);
    $current_user->isAuthenticated()->willReturn(FALSE);
    $time = $this->prophesize(TimeInterface::class);

    return new OverrideEndpointQuery(
      $config_service,
      $this->prophesize(LoggerChannelFactoryInterface::class)->reveal(),
      $this->prophesize(KillSwitch::class)->reveal(),
      $http_client,
      $current_user->reveal(),
      $time->reveal(),
      $remote_cache,
    );
  }

  /**
   * Create an endpoint query wired to a specific HTTP client.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   The endpoint query.
   */
  private function createEndpointQueryWithClient(ClientInterface $http_client): EndpointQuery {
    $config_factory = $this->getConfigFactoryStub([
      'hpc_api.settings' => [
        'url' => 'https://api.hpc.tools',
        'default_api_version' => 'v1',
        'auth_username' => 'authname',
        'auth_password' => 'authpass',
        'api_key' => 'apikey123',
        'public_base_path' => 'public/fts',
        'connect_timeout' => 3,
        'timeout' => 25,
        'flow_custom_search_timeout' => 6,
        'cache_lifetime' => 3600,
        'use_gzip_compression' => FALSE,
      ],
    ]);
    $config_service = new ConfigService($config_factory);
    $current_user = $this->prophesize(AccountProxyInterface::class);
    $current_user->isAuthenticated()->willReturn(FALSE);
    $time = $this->prophesize(TimeInterface::class);

    return new OverrideEndpointQuery(
      $config_service,
      $this->prophesize(LoggerChannelFactoryInterface::class)->reveal(),
      $this->prophesize(KillSwitch::class)->reveal(),
      $http_client,
      $current_user->reveal(),
      $time->reveal(),
    );
  }

  /**
   * Mock a HTTP client that records GET request options.
   *
   * @param string $payload
   *   The response body.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client.
   */
  private function mockCapturingHttpClient(string $payload): ClientInterface {
    return new class($payload) extends Client {

      /**
       * Captured requests.
       *
       * @var array
       */
      public array $requests = [];

      /**
       * Constructs the test client.
       */
      public function __construct(private readonly string $payload) {}

      /**
       * {@inheritdoc}
       */
      public function get($uri, array $options = []): ResponseInterface {
        $this->requests[] = [
          'uri' => $uri,
          'options' => $options,
        ];
        return new Response(200, [], $this->payload);
      }

    };
  }

  /**
   * Mock an HTTP client that fails the test if an endpoint is called.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The HTTP client test double.
   */
  private function mockHttpClientThatFailsOnGet(): ClientInterface {
    return new class extends Client {

      /**
       * {@inheritdoc}
       */
      public function get($uri, array $options = []): ResponseInterface {
        Assert::fail('Endpoint HTTP get must not be called on a remote data cache hit.');
        throw new \LogicException('Unreachable.');
      }

    };
  }

  /**
   * Mock a remote data cache service.
   *
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheItem $item
   *   The remote data cache item.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The remote data cache prophecy.
   */
  private function mockRemoteDataCache(RemoteDataCacheItem $item) {
    $remote_cache = $this->prophesize(RemoteDataCacheInterface::class);
    $remote_cache->isEnabled()->willReturn(TRUE);
    $remote_cache->buildCid('hpc_api_endpoint', Argument::type('string'))->willReturn('hpc_api_endpoint:test');
    $remote_cache->get('hpc_api_endpoint:test')->willReturn($item);
    return $remote_cache;
  }

  /**
   * Create a remote data cache item.
   *
   * @param string $payload
   *   The raw endpoint response body.
   * @param int $request_time
   *   The request time.
   * @param int $fresh_until
   *   The fresh-until timestamp.
   * @param int $stale_until
   *   The stale-until timestamp.
   * @param int $fetched
   *   The fetched timestamp.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The remote data cache item.
   */
  private function createRemoteDataCacheItem(string $payload, int $request_time, int $fresh_until, int $stale_until, int $fetched = 100): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      'hpc_api_endpoint:test',
      'hpc_api_endpoint',
      'https://api.hpc.tools/v1/fts/flow/usage-years/location/1',
      '',
      ['auth_method' => EndpointQuery::AUTH_METHOD_BASIC],
      $payload,
      100,
      100,
      $fetched,
      $fresh_until,
      $stale_until,
      FALSE,
      0,
      0,
      100,
      0,
      NULL,
      strlen(serialize($payload)),
      $request_time,
    );
  }

  /**
   * Prepare usage year api mock response.
   */
  protected function getUsageYearApiMockResponse() {
    return [
      '2000', '2001', '2002', '2003', '2004', '2005', '2006', '2007', '2008', '2009', '2010', '2011', '2012',
      '2013', '2014', '2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024',
    ];
  }

  /**
   * Prepare usage year api mock response.
   */
  protected function getProjectPlanApiMockResponse() {
    $response = new \stdClass();

    $response->report1 = new \stdClass();
    $response->report1->fundingTotals = new \stdClass();
    $response->report1->pledgeTotals = new \stdClass();

    $response->report3 = new \stdClass();
    $response->report3->fundingTotals = new \stdClass();
    $response->report3->pledgeTotals = new \stdClass();

    $response->report4 = new \stdClass();
    $response->report4->fundingTotals = new \stdClass();

    $response->requirements = new \stdClass();

    $response->report1->fundingTotals->total = 715210134;
    $response->report1->pledgeTotals->total = 0;

    $report3_funding_single_funding_objects = new \stdClass();
    $report3_funding_single_funding_objects->type = 'Plan';
    $report3_funding_single_funding_objects->direction = 'destination';
    $report3_funding_single_funding_objects->id = 642;
    $report3_funding_single_funding_objects->name = 'Nigeria 2018';
    $report3_funding_single_funding_objects->totalFunding = 715210134;

    $report3_funding_totals_objects = new \stdClass();
    $report3_funding_totals_objects->type = 'Plan';
    $report3_funding_totals_objects->direction = 'destination';
    $report3_funding_totals_objects->singleFundingTotal = 715210134;
    $report3_funding_totals_objects->singleFundingObjects = [$report3_funding_single_funding_objects];

    $response->report3->fundingTotals->total = 715210134;
    $response->report3->fundingTotals->objects = [$report3_funding_totals_objects];

    $response->report3->pledgeTotals->total = 0;
    $response->report3->pledgeTotals->objects = [];

    $response->report4->fundingTotals->total = 44761512;

    $response->requirements->totalRevisedReqs = 1047768587;
    $response->requirements->totalOrigReqs = 1047768587;

    $requirements_object = new \stdClass();
    $requirements_object->id = 642;
    $requirements_object->name = 'Nigeria 2018';
    $requirements_object->objectType = 'Plan';
    $requirements_object->revisedRequirements = 1047768587;
    $requirements_object->origRequirements = 1047768587;

    $response->requirements->objects = [$requirements_object];

    return $response;
  }

}
