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

    $this->assertNull($query->getClusterSummaryData());
    $this->assertNull($query->getClusterSummaryData());
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
   * Tests that omitted clusters in valid summary data are unavailable.
   */
  public function testOmittedClusterFundingIsUnavailable(): void {
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery($this->createClusterSummaryEndpointData(), $cache, $set_cache_calls, $get_data_calls);

    $this->assertSame(4304843.0, $query->getClusterTotalFunding(4571));
    $this->assertNull($query->getClusterTotalFunding(9999));
    $this->assertNull($query->getClusterFundingGap(9999, 1000000));
    $this->assertNull($query->getClusterFundingCoverage(9999, 1000000));
    $this->assertNull($query->getClusterFundingCoverage(4571, NULL));
  }

  /**
   * Tests that unavailable summary data is not invented as zero funding.
   */
  public function testUnavailableClusterSummaryDoesNotInventZero(): void {
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery(FALSE, $cache, $set_cache_calls, $get_data_calls);

    $this->assertNull($query->getClusterTotalFunding(9999));
    $this->assertNull($query->getClusterFundingGap(9999, 1000000));
    $this->assertNull($query->getClusterFundingCoverage(9999, 1000000));
  }

  /**
   * Tests that a reported zero funding value is still returned as zero.
   */
  public function testReportedZeroClusterFundingIsPreserved(): void {
    $endpoint_data = $this->createClusterSummaryEndpointData();
    $endpoint_data->report3->fundingTotals->objects[0]->objectsBreakdown[0]->totalFunding = 0;
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery($endpoint_data, $cache, $set_cache_calls, $get_data_calls);

    $this->assertSame(0.0, $query->getClusterTotalFunding(4571));
    $this->assertNull($query->getClusterTotalFunding(9999));
  }

  /**
   * Tests that incomplete summaries fall back to previous complete summaries.
   */
  public function testIncompleteClusterSummaryFallsBackToLastGoodData(): void {
    $endpoint_data = $this->createClusterSummaryEndpointData();
    $this->addClusterSummaryRequirement($endpoint_data, 4571);
    $this->addClusterSummaryRequirement($endpoint_data, 9999);
    $this->addClusterSummaryFunding($endpoint_data, 9999, 250000);
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery($endpoint_data, $cache, $set_cache_calls, $get_data_calls);

    $this->assertSame(250000.0, $query->getClusterTotalFunding(9999));

    drupal_static_reset(FlowSearchQuery::class . '::getClusterSummaryData');
    $cache['cluster-summary-cache-key']['valid'] = FALSE;
    $this->removeClusterSummaryFunding($endpoint_data, 9999);

    $this->assertSame(250000.0, $query->getClusterTotalFunding(9999));

    drupal_static_reset(FlowSearchQuery::class . '::getClusterSummaryData');
    $this->removeClusterSummaryFunding($endpoint_data, 4571);

    $this->assertSame(4304843.0, $query->getClusterTotalFunding(4571));
    $this->assertSame(250000.0, $query->getClusterTotalFunding(9999));
    $this->assertSame(5, $get_data_calls);
  }

  /**
   * Tests incomplete endpoint cache repair through one direct retry.
   */
  public function testIncompleteClusterSummaryRetriesWithoutRawCache(): void {
    $cached_endpoint_data = $this->createClusterSummaryEndpointData();
    $this->addClusterSummaryRequirement($cached_endpoint_data, 4571);
    $this->addClusterSummaryRequirement($cached_endpoint_data, 9999);

    $live_endpoint_data = $this->createClusterSummaryEndpointData();
    $this->addClusterSummaryRequirement($live_endpoint_data, 4571);
    $this->addClusterSummaryRequirement($live_endpoint_data, 9999);
    $this->addClusterSummaryFunding($live_endpoint_data, 9999, 250000);

    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $reset_cache_calls = 0;
    $query = $this->mockFlowSearchQuery(
      $cached_endpoint_data,
      $cache,
      $set_cache_calls,
      $get_data_calls,
      $reset_cache_calls,
      $live_endpoint_data
    );

    $this->assertSame(250000.0, $query->getClusterTotalFunding(9999));
    $this->assertSame(2, $get_data_calls);
    $this->assertSame(1, $set_cache_calls);
  }

  /**
   * Tests that incomplete summaries are not promoted to last-good cache data.
   */
  public function testIncompleteClusterSummaryIsNotStoredAsLastGoodData(): void {
    $endpoint_data = $this->createClusterSummaryEndpointData();
    $this->addClusterSummaryRequirement($endpoint_data, 4571);
    $this->addClusterSummaryRequirement($endpoint_data, 9999);
    $cache = [];
    $set_cache_calls = 0;
    $get_data_calls = 0;
    $query = $this->mockFlowSearchQuery($endpoint_data, $cache, $set_cache_calls, $get_data_calls);

    $this->assertNull($query->getClusterTotalFunding(9999));
    $this->assertSame(0, $set_cache_calls);
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
   * @param mixed $uncached_endpoint_data
   *   Optional endpoint data returned while cache is disabled.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   *   The flow search query.
   */
  private function mockFlowSearchQuery(mixed $endpoint_data, array &$cache, int &$set_cache_calls = 0, int &$get_data_calls = 0, int &$reset_cache_calls = 0, mixed $uncached_endpoint_data = NULL): FlowSearchQuery {
    $endpoint_query = $this->mockEndpointQuery($endpoint_data, $get_data_calls, $uncached_endpoint_data);

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
      ->onlyMethods(['getCachedClusterSummaryData', 'getCache', 'setCache', 'cache'])
      ->getMock();

    $query->method('getCachedClusterSummaryData')->willReturnCallback(function ($cache_key, $allow_invalid = FALSE) use (&$cache) {
      $summary_data = $this->getCachedTestSummaryData($cache, $allow_invalid);
      return $this->isValidTestSummaryData($summary_data) ? $summary_data : NULL;
    });
    $query->method('getCache')->willReturnCallback(function () use (&$cache) {
      return $this->getCachedTestSummaryData($cache);
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
      $cache['cluster-summary-cache-key'] = [
        'data' => $data,
        'valid' => TRUE,
      ];
    });
    return $query;
  }

  /**
   * Get cached test summary data.
   *
   * @param array $cache
   *   The test cache storage.
   * @param bool $allow_invalid
   *   Whether invalid cache entries may be returned.
   *
   * @return mixed
   *   The cached data.
   */
  private function getCachedTestSummaryData(array $cache, bool $allow_invalid = FALSE): mixed {
    $cache_item = $cache['cluster-summary-cache-key'] ?? NULL;
    if (is_array($cache_item) && array_key_exists('data', $cache_item)) {
      return $allow_invalid || !empty($cache_item['valid']) ? $cache_item['data'] : NULL;
    }
    return $cache_item;
  }

  /**
   * Check if the given test summary has the expected structure.
   *
   * @param mixed $summary_data
   *   The summary data.
   *
   * @return bool
   *   TRUE if the summary is valid, FALSE otherwise.
   */
  private function isValidTestSummaryData(mixed $summary_data): bool {
    return is_object($summary_data) && property_exists($summary_data, 'clusters') && is_array($summary_data->clusters) && property_exists($summary_data, 'totals') && is_object($summary_data->totals);
  }

  /**
   * Mock an endpoint query.
   *
   * @param mixed $endpoint_data
   *   The endpoint data returned by the endpoint query.
   * @param int $get_data_calls
   *   Endpoint get-data call counter.
   * @param mixed $uncached_endpoint_data
   *   Optional endpoint data returned while cache is disabled.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery
   *   The mocked endpoint query.
   */
  private function mockEndpointQuery(mixed $endpoint_data, int &$get_data_calls, mixed $uncached_endpoint_data = NULL): EndpointQuery {
    $placeholders = ['plan_id' => 718];
    $use_cache = TRUE;
    $endpoint_query = $this->getMockBuilder(EndpointQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'setArguments',
        'setPlaceholders',
        'getPlaceholders',
        'setEndpointArguments',
        'useCache',
        'setUseCache',
        'getData',
      ])
      ->getMock();
    $endpoint_query->method('setPlaceholders')->willReturnCallback(function ($new_placeholders) use (&$placeholders) {
      $placeholders = $new_placeholders + $placeholders;
    });
    $endpoint_query->method('getPlaceholders')->willReturnCallback(function () use (&$placeholders) {
      return $placeholders;
    });
    $endpoint_query->method('useCache')->willReturnCallback(function () use (&$use_cache) {
      return $use_cache;
    });
    $endpoint_query->method('setUseCache')->willReturnCallback(function ($status = TRUE) use (&$use_cache) {
      $use_cache = $status;
    });
    $endpoint_query->method('getData')->willReturnCallback(function () use ($endpoint_data, $uncached_endpoint_data, &$get_data_calls, &$use_cache) {
      $get_data_calls++;
      return !$use_cache && $uncached_endpoint_data !== NULL ? $uncached_endpoint_data : $endpoint_data;
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
      'requirements' => (object) [
        'objects' => [],
      ],
    ];
  }

  /**
   * Add a requirement row to endpoint data.
   *
   * @param object $endpoint_data
   *   The endpoint data.
   * @param int $cluster_id
   *   The cluster id.
   */
  private function addClusterSummaryRequirement(object $endpoint_data, int $cluster_id): void {
    $endpoint_data->requirements->objects[] = (object) [
      'id' => $cluster_id,
      'objectType' => 'Cluster',
      'revisedRequirements' => 1000000,
      'origRequirements' => 1000000,
    ];
  }

  /**
   * Add a funding row to endpoint data.
   *
   * @param object $endpoint_data
   *   The endpoint data.
   * @param int $cluster_id
   *   The cluster id.
   * @param float $total_funding
   *   The total funding.
   */
  private function addClusterSummaryFunding(object $endpoint_data, int $cluster_id, float $total_funding): void {
    $endpoint_data->report3->fundingTotals->objects[0]->objectsBreakdown[] = (object) [
      'id' => $cluster_id,
      'name' => 'Shelter',
      'totalFunding' => $total_funding,
    ];
  }

  /**
   * Remove a funding row from endpoint data.
   *
   * @param object $endpoint_data
   *   The endpoint data.
   * @param int $cluster_id
   *   The cluster id.
   */
  private function removeClusterSummaryFunding(object $endpoint_data, int $cluster_id): void {
    $endpoint_data->report3->fundingTotals->objects[0]->objectsBreakdown = array_values(array_filter($endpoint_data->report3->fundingTotals->objects[0]->objectsBreakdown, fn ($cluster) => $cluster->id != $cluster_id));
  }

}
