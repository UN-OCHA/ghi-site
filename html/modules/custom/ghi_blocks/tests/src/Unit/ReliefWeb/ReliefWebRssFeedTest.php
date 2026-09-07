<?php

namespace Drupal\Tests\ghi_blocks\Unit\ReliefWeb;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed;
use Drupal\hpc_remote_data_cache\RemoteDataCacheInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * @covers \Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed
 *
 * @group ghi_blocks
 */
class ReliefWebRssFeedTest extends UnitTestCase {

  private const FEED_URL = 'https://reliefweb.int/country/ven/rss.xml?format=10';

  /**
   * Tests feed URL validation.
   *
   * @param string $url
   *   The URL to validate.
   * @param bool $expected
   *   The expected result.
   *
   * @dataProvider feedUrlValidationProvider
   */
  public function testFeedUrlValidation(string $url, bool $expected): void {
    $this->assertSame($expected, $this->createService()->isValidFeedUrl($url));
  }

  /**
   * Data provider for testFeedUrlValidation().
   *
   * @return array
   *   The test cases.
   */
  public function feedUrlValidationProvider(): array {
    return [
      'reliefweb country feed' => [self::FEED_URL, TRUE],
      'reliefweb filtered updates feed' => [
        'https://reliefweb.int/updates/rss.xml?advanced-search=%28C254%29_%28F10%29',
        TRUE,
      ],
      'http reliefweb URL' => ['http://reliefweb.int/country/ven/rss.xml?format=10', FALSE],
      'non-RSS reliefweb URL' => ['https://reliefweb.int/country/ven', FALSE],
      'external RSS URL' => ['https://example.com/country/ven/rss.xml?format=10', FALSE],
      'lookalike host' => ['https://not-reliefweb.int/country/ven/rss.xml?format=10', FALSE],
    ];
  }

  /**
   * Tests parsing feed item fields from ReliefWeb RSS XML.
   */
  public function testParseFeedItems(): void {
    $items = $this->createService()->parseFeedItems($this->getFeedXml());

    $this->assertIsArray($items);
    $this->assertCount(2, $items);
    $this->assertSame('UNICEF Venezuela Humanitarian Situation Report No. 8', $items[0]['title']);
    $this->assertSame('https://reliefweb.int/report/venezuela-bolivarian-republic/unicef-venezuela-humanitarian-situation-report-no-8', $items[0]['url']);
    $this->assertSame(strtotime('Wed, 02 Sep 2026 07:18:39 +0000'), $items[0]['timestamp']);
    $this->assertSame("UN Children's Fund", $items[0]['source']);
    $this->assertSame('Emergency Telecommunications Cluster, Logistics Cluster', $items[1]['source']);
  }

  /**
   * Tests fetching, parsing and storing a feed response.
   */
  public function testGetItemsFetchesAndCachesResponse(): void {
    $remote_cache = $this->createMock(RemoteDataCacheInterface::class);
    $remote_cache->method('isEnabled')->willReturn(TRUE);
    $remote_cache->expects($this->exactly(2))
      ->method('buildCid')
      ->with(ReliefWebRssFeed::SOURCE, self::FEED_URL)
      ->willReturn('reliefweb:test');
    $remote_cache->expects($this->once())
      ->method('get')
      ->with('reliefweb:test')
      ->willReturn(NULL);
    $remote_cache->expects($this->once())
      ->method('set')
      ->with(
        'reliefweb:test',
        $this->callback(fn (array $payload) => count($payload) === 2 && $payload[0]['title'] === 'UNICEF Venezuela Humanitarian Situation Report No. 8'),
        $this->callback(fn (array $metadata) => $metadata['refresher_id'] === ReliefWebRssFeed::REFRESHER_ID && $metadata['endpoint_url'] === self::FEED_URL && in_array($metadata['cache_tags'][0], [ReliefWebRssFeed::SOURCE . ':' . hash('sha256', self::FEED_URL)], TRUE))
      );

    $items = $this->createService($this->mockHttpClient($this->getFeedXml()), $remote_cache)->getItems(self::FEED_URL, 1);

    $this->assertCount(1, $items);
    $this->assertSame('UNICEF Venezuela Humanitarian Situation Report No. 8', $items[0]['title']);
  }

  /**
   * Tests that stale cache items are served and queued for refresh.
   */
  public function testGetItemsServesStaleCacheAndQueuesRefresh(): void {
    $payload = [
      [
        'title' => 'Cached report',
        'url' => 'https://reliefweb.int/report/venezuela-bolivarian-republic/cached-report',
        'timestamp' => strtotime('Wed, 02 Sep 2026 07:18:39 +0000'),
        'source' => 'Cached source',
      ],
    ];
    $cache_item = $this->createRemoteCacheItem($payload, 1300, 1200, 1600);

    $remote_cache = $this->createMock(RemoteDataCacheInterface::class);
    $remote_cache->method('isEnabled')->willReturn(TRUE);
    $remote_cache->expects($this->once())
      ->method('buildCid')
      ->with(ReliefWebRssFeed::SOURCE, self::FEED_URL)
      ->willReturn('reliefweb:test');
    $remote_cache->expects($this->once())
      ->method('get')
      ->with('reliefweb:test')
      ->willReturn($cache_item);
    $remote_cache->expects($this->once())
      ->method('queueRefresh')
      ->with($cache_item)
      ->willReturn(TRUE);
    $remote_cache->expects($this->never())->method('set');

    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects($this->never())->method('request');

    $items = $this->createService($http_client, $remote_cache)->getItems(self::FEED_URL, 1);

    $this->assertSame($payload, $items);
  }

  /**
   * Create the service under test.
   *
   * @param \GuzzleHttp\ClientInterface|null $http_client
   *   The HTTP client.
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheInterface|null $remote_cache
   *   The remote cache.
   *
   * @return \Drupal\ghi_blocks\ReliefWeb\ReliefWebRssFeed
   *   The service.
   */
  private function createService(?ClientInterface $http_client = NULL, ?RemoteDataCacheInterface $remote_cache = NULL): ReliefWebRssFeed {
    $logger_factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $logger_factory->method('get')->willReturn($this->createMock(LoggerChannelInterface::class));
    return new ReliefWebRssFeed($http_client ?? $this->createMock(ClientInterface::class), $logger_factory, $remote_cache);
  }

  /**
   * Mock an HTTP client returning the given body.
   *
   * @param string $body
   *   The response body.
   *
   * @return \GuzzleHttp\ClientInterface
   *   The mocked HTTP client.
   */
  private function mockHttpClient(string $body): ClientInterface {
    $http_client = $this->createMock(ClientInterface::class);
    $http_client->expects($this->once())
      ->method('request')
      ->with('GET', self::FEED_URL, $this->callback(fn (array $options) => $options['http_errors'] === FALSE))
      ->willReturn(new Response(200, ['Content-Type' => 'application/rss+xml'], $body));
    return $http_client;
  }

  /**
   * Create a test remote cache item.
   *
   * @param array $payload
   *   The payload.
   * @param int $request_time
   *   The request timestamp.
   * @param int $fresh_until
   *   The fresh-until timestamp.
   * @param int $stale_until
   *   The stale-until timestamp.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The test item.
   */
  private function createRemoteCacheItem(array $payload, int $request_time, int $fresh_until, int $stale_until): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      'reliefweb:test',
      ReliefWebRssFeed::REFRESHER_ID,
      self::FEED_URL,
      '',
      [],
      $payload,
      1000,
      1000,
      1000,
      $fresh_until,
      $stale_until,
      FALSE,
      0,
      0,
      1000,
      0,
      NULL,
      strlen(serialize($payload)),
      $request_time,
      [ReliefWebRssFeed::SOURCE . ':' . hash('sha256', self::FEED_URL)],
    );
  }

  /**
   * Get sample ReliefWeb RSS XML.
   *
   * @return string
   *   The fixture XML.
   */
  private function getFeedXml(): string {
    return <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<rss version="2.0">
  <channel>
    <title>ReliefWeb - Situation Report - Venezuela Updates</title>
    <item>
      <title>UNICEF Venezuela Humanitarian Situation Report No. 8</title>
      <link>https://reliefweb.int/report/venezuela-bolivarian-republic/unicef-venezuela-humanitarian-situation-report-no-8</link>
      <pubDate>Wed, 02 Sep 2026 07:18:39 +0000</pubDate>
      <description><![CDATA[<div class="tag source">Source: UN Children's Fund</div>]]></description>
      <author>UN Children's Fund</author>
    </item>
    <item>
      <title>Venezuela: Earthquakes - LTC Situation Report #07</title>
      <link>https://reliefweb.int/report/venezuela-bolivarian-republic/venezuela-earthquakes-ltc-situation-report-telecoms-07</link>
      <pubDate>Tue, 01 Sep 2026 12:47:31 +0000</pubDate>
      <description><![CDATA[<div class="tag source">Sources: Emergency Telecommunications Cluster, Logistics Cluster</div>]]></description>
    </item>
  </channel>
</rss>
XML;
  }

}
