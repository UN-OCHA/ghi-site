<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the flow search endpoint query plugin.
 *
 * @covers \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
 *
 * @group ghi_plans
 */
class FlowSearchQueryTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::setContainer(new ContainerBuilder());
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    drupal_static_reset(FlowSearchQuery::class . '::getClusterSummaryData');
    parent::tearDown();
  }

  /**
   * Tests that persisted empty summaries are ignored.
   */
  public function testPersistedEmptyClusterSummaryIsIgnored(): void {
    $cache = ['cluster-summary-cache-key' => []];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $reset_cache_calls = 0;
    $query = $this->mockFlowSearchQuery($this->createClusterSummaryEndpointData(), $cache, $set_cache_calls, $get_data_calls, $reset_cache_calls);

    $summary = $query->getClusterSummaryData();

    $this->assertSame(1, $get_data_calls);
    $this->assertSame(1, $set_cache_calls);
    $this->assertSame(1, $reset_cache_calls);
    $this->assertSame(4304843, $summary->clusters[0]->total_funding);
  }

  /**
   * Tests failed endpoint responses are only cached for the current request.
   */
  public function testFailedClusterSummaryIsCachedForCurrentRequestOnly(): void {
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery(FALSE, $cache, $set_cache_calls, $get_data_calls);

    $this->assertSame([], $query->getClusterSummaryData());
    $this->assertSame([], $query->getClusterSummaryData());
    $this->assertSame(1, $get_data_calls);
    $this->assertSame(0, $set_cache_calls);
  }

  /**
   * Tests valid endpoint responses are transformed and persisted.
   */
  public function testValidClusterSummaryIsPersisted(): void {
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery($this->createClusterSummaryEndpointData(), $cache, $set_cache_calls, $get_data_calls);

    $summary = $query->getClusterSummaryData();

    $this->assertSame(1, $get_data_calls);
    $this->assertSame(1, $set_cache_calls);
    $this->assertCount(1, $summary->clusters);
    $this->assertSame(4304843, $summary->clusters[0]->total_funding);
    $this->assertSame(129756095, $summary->totals->total_funding);
  }

  /**
   * Mock a flow search query under test.
   *
   * @param mixed $endpoint_data
   *   The endpoint data returned by the endpoint query.
   * @param array $cache
   *   The persistent processed-data cache.
   * @param int $set_cache_calls
   *   Set-cache call counter.
   * @param int $get_data_calls
   *   Endpoint get-data call counter.
   * @param int $reset_cache_calls
   *   Cache reset call counter.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   *   The flow search query.
   */
  private function mockFlowSearchQuery(mixed $endpoint_data, array &$cache, int &$set_cache_calls = 0, int &$get_data_calls = 0, int &$reset_cache_calls = 0): FlowSearchQuery {
    $endpoint_query = $this->mockEndpointQuery($endpoint_data, $get_data_calls);

    $query = $this->getMockBuilder(FlowSearchQuery::class)
      ->setConstructorArgs([
        [],
        'flow_search_query',
        [
          'endpoint' => [
            'public' => 'fts/flow/custom-search',
            'version' => 'v1',
          ],
        ],
        $endpoint_query,
        $this->prophesize(AccountProxyInterface::class)->reveal(),
        $this->prophesize(CacheBackendInterface::class)->reveal(),
      ])
      ->onlyMethods(['getCache', 'setCache', 'cache'])
      ->getMock();

    $query->method('getCache')->willReturnCallback(function () use (&$cache) {
      return $cache['cluster-summary-cache-key'] ?? NULL;
    });
    $query->method('cache')->willReturnCallback(function ($cache_key, $data = NULL, $reset = FALSE) use (&$cache, &$reset_cache_calls) {
      if ($data === NULL && $reset === TRUE) {
        $reset_cache_calls++;
        unset($cache['cluster-summary-cache-key']);
      }
      return NULL;
    });
    $query->method('setCache')->willReturnCallback(function ($cache_key, $data) use (&$cache, &$set_cache_calls) {
      $set_cache_calls++;
      $cache['cluster-summary-cache-key'] = $data;
    });
    return $query;
  }

  /**
   * Mock an endpoint query.
   *
   * @param mixed $endpoint_data
   *   The endpoint data returned by the endpoint query.
   * @param int $get_data_calls
   *   Endpoint get-data call counter.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   The mocked endpoint query.
   */
  private function mockEndpointQuery(mixed $endpoint_data, int &$get_data_calls): EndpointQuery {
    $placeholders = ['plan_id' => 718];
    $endpoint_query = $this->getMockBuilder(EndpointQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'setArguments',
        'setPlaceholders',
        'getPlaceholders',
        'setEndpointArguments',
        'getData',
      ])
      ->getMock();
    $endpoint_query->method('setPlaceholders')->willReturnCallback(function ($new_placeholders) use (&$placeholders) {
      $placeholders = $new_placeholders + $placeholders;
    });
    $endpoint_query->method('getPlaceholders')->willReturnCallback(function () use (&$placeholders) {
      return $placeholders;
    });
    $endpoint_query->method('getData')->willReturnCallback(function () use ($endpoint_data, &$get_data_calls) {
      $get_data_calls++;
      return $endpoint_data;
    });
    return $endpoint_query;
  }

  /**
   * Create endpoint data for a valid cluster summary.
   *
   * @return object
   *   The endpoint data.
   */
  private function createClusterSummaryEndpointData(): object {
    return (object) [
      'report3' => (object) [
        'fundingTotals' => (object) [
          'objects' => [
            (object) [
              'objectsBreakdown' => [
                (object) [
                  'id' => 4571,
                  'name' => 'Coordination',
                  'totalFunding' => 4304843,
                ],
              ],
              'totalBreakdown' => (object) [
                'objectsSum' => 123901299,
                'overlapCorrection' => 0,
                'sharedFunding' => 5854796,
                'totalFunding' => 129756095,
              ],
            ],
          ],
        ],
      ],
    ];
  }

}
