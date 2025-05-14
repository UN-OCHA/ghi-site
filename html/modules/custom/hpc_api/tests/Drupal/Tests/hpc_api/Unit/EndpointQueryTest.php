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
use GuzzleHttp\Psr7\Response;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

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
        'timeout' => 30,
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
