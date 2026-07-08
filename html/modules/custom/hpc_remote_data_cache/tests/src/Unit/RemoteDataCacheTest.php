<?php

namespace Drupal\Tests\hpc_remote_data_cache\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_remote_data_cache\RemoteDataCache;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherManager;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherInterface;
use Drupal\hpc_remote_data_cache\RemoteDataCacheRefreshResult;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \Drupal\hpc_remote_data_cache\RemoteDataCache
 *
 * @group HPC Remote Data Cache
 */
class RemoteDataCacheTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * Test that successful payloads are stored in the permanent cache bin.
   */
  public function testSetStoresPermanentCacheEntry(): void {
    $payload = (object) ['value' => 'cached'];
    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $cache_backend->get('fabric:test')->willReturn(FALSE);
    $cache_backend->set('fabric:test', Argument::that(function (array $data) use ($payload) {
      return $data['payload'] === $payload
        && $data['created'] === 1000
        && $data['fresh_until'] === 1060
        && $data['stale_until'] === 1180
        && $data['cache_tags'] === ['remote:article:1']
        && $data['refresher_id'] === 'fabric_graphql';
    }), Cache::PERMANENT, Argument::that(fn (array $tags) => in_array('remote:article:1', $tags, TRUE)))->shouldBeCalledOnce();

    $cache = $this->createRemoteDataCache($cache_backend->reveal(), 1000);
    $cache->set('fabric:test', $payload, [
      'refresher_id' => 'fabric_graphql',
      'endpoint_url' => 'https://fabric.example.test/graphql',
      'request_body' => '{}',
      'context' => ['query' => 'query { plans { items { Id } } }'],
      'cache_tags' => ['remote:article:1'],
      'fresh_ttl' => 60,
      'stale_ttl' => 120,
    ]);
  }

  /**
   * Test that cached data is rebuilt as an item.
   */
  public function testGetBuildsCacheItemFromPermanentCacheEntry(): void {
    $payload = (object) ['value' => 'cached'];
    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $cache_backend->get('fabric:test')->willReturn((object) [
      'data' => $this->createItemData($payload),
    ]);

    $cache = $this->createRemoteDataCache($cache_backend->reveal(), 1100);
    $item = $cache->get('fabric:test');

    $this->assertInstanceOf(RemoteDataCacheItem::class, $item);
    $this->assertSame($payload, $item->getPayload());
    $this->assertSame(['remote:article:1'], $item->getCacheTags());
    $this->assertSame(RemoteDataCacheItem::STATE_STALE, $item->getState());
  }

  /**
   * Test that queueRefresh records queued state and creates one queue item.
   */
  public function testQueueRefreshUpdatesCacheMetadataAndQueuesWork(): void {
    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $cache_backend->get('fabric:test')->willReturn((object) [
      'data' => $this->createItemData((object) ['value' => 'cached']),
    ]);
    $cache_backend->set('fabric:test', Argument::that(function (array $data) {
      return $data['refresh_queued'] === TRUE && $data['changed'] === 1100;
    }), Cache::PERMANENT, Argument::type('array'))->shouldBeCalledOnce();

    $queue = $this->prophesize(QueueInterface::class);
    $queue->createItem(Argument::that(fn (object $data) => $data->cid === 'fabric:test'))->shouldBeCalledOnce();
    $queue_factory = $this->prophesize(QueueFactory::class);
    $queue_factory->get('hpc_remote_data_cache_refresh')->willReturn($queue->reveal());

    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire('hpc_remote_data_cache:fabric:test:queue', 30)->willReturn(TRUE);
    $lock->release('hpc_remote_data_cache:fabric:test:queue')->shouldBeCalledOnce();

    $cache = $this->createRemoteDataCache($cache_backend->reveal(), 1100, $queue_factory->reveal(), $lock->reveal());
    $this->assertTrue($cache->queueRefresh('fabric:test'));
  }

  /**
   * Test that refresh preserves the item's existing fresh and stale TTLs.
   */
  public function testRefreshPreservesCacheItemTtls(): void {
    $old_payload = (object) ['value' => 'old'];
    $new_payload = (object) ['value' => 'new'];
    $item_data = array_replace($this->createItemData($old_payload), [
      'fresh_until' => 1300,
      'stale_until' => 1900,
    ]);

    $cache_backend = $this->prophesize(CacheBackendInterface::class);
    $cache_backend->get('fabric:test')->willReturn((object) [
      'data' => $item_data,
    ]);
    $cache_backend->set('fabric:test', Argument::that(function (array $data) {
      return $data['refresh_queued'] === FALSE && $data['refreshing_until'] === 2300;
    }), Cache::PERMANENT, Argument::type('array'))->shouldBeCalled();
    $cache_backend->set('fabric:test', Argument::that(function (array $data) use ($new_payload) {
      return $data['payload'] === $new_payload
        && $data['fresh_until'] === 2300
        && $data['stale_until'] === 2900;
    }), Cache::PERMANENT, Argument::type('array'))->shouldBeCalled();

    $lock = $this->prophesize(LockBackendInterface::class);
    $lock->acquire('hpc_remote_data_cache:fabric:test:refresh', 300)->willReturn(TRUE);
    $lock->release('hpc_remote_data_cache:fabric:test:refresh')->shouldBeCalledOnce();

    $refresher = $this->prophesize(RemoteDataCacheRefresherInterface::class);
    $refresher->refresh(Argument::type(RemoteDataCacheItem::class))
      ->willReturn(RemoteDataCacheRefreshResult::success($new_payload));
    $refresher_manager = $this->prophesize(RemoteDataCacheRefresherManager::class);
    $refresher_manager->createInstance('fabric_graphql')->willReturn($refresher->reveal());

    $cache = $this->createRemoteDataCache($cache_backend->reveal(), 2000, NULL, $lock->reveal(), $refresher_manager->reveal());
    $this->assertTrue($cache->refresh('fabric:test'));
  }

  /**
   * Create the service under test.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param int $request_time
   *   The request time.
   * @param \Drupal\Core\Queue\QueueFactory|null $queue_factory
   *   The queue factory.
   * @param \Drupal\Core\Lock\LockBackendInterface|null $lock
   *   The lock backend.
   * @param \Drupal\hpc_remote_data_cache\RemoteDataCacheRefresherManager|null $refresher_manager
   *   The refresher plugin manager.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCache
   *   The remote data cache service.
   */
  private function createRemoteDataCache(CacheBackendInterface $cache_backend, int $request_time, ?QueueFactory $queue_factory = NULL, ?LockBackendInterface $lock = NULL, ?RemoteDataCacheRefresherManager $refresher_manager = NULL): RemoteDataCache {
    $time = $this->prophesize(TimeInterface::class);
    $time->getRequestTime()->willReturn($request_time);

    return new RemoteDataCache(
      $cache_backend,
      $queue_factory ?? $this->prophesize(QueueFactory::class)->reveal(),
      $lock ?? $this->prophesize(LockBackendInterface::class)->reveal(),
      $time->reveal(),
      $this->getConfigFactoryStub([
        'hpc_remote_data_cache.settings' => [
          'enabled' => TRUE,
          'default_fresh_ttl' => 60,
          'default_stale_ttl' => 120,
          'refresh_lock_ttl' => 300,
          'refresh_retry_base' => 300,
          'serve_expired_on_error' => TRUE,
          'max_payload_size' => 0,
        ],
      ]),
      $this->prophesize(LoggerChannelFactoryInterface::class)->reveal(),
      $refresher_manager ?? $this->prophesize(RemoteDataCacheRefresherManager::class)->reveal(),
    );
  }

  /**
   * Create stored item data.
   *
   * @param mixed $payload
   *   The cached payload.
   *
   * @return array
   *   The stored item data.
   */
  private function createItemData(mixed $payload): array {
    return [
      'refresher_id' => 'fabric_graphql',
      'endpoint_url' => 'https://fabric.example.test/graphql',
      'request_body' => '{}',
      'context' => [],
      'payload' => $payload,
      'cache_tags' => ['remote:article:1'],
      'payload_size' => strlen(serialize($payload)),
      'created' => 1000,
      'changed' => 1000,
      'fetched' => 1000,
      'fresh_until' => 1050,
      'stale_until' => 1200,
      'refresh_queued' => FALSE,
      'refreshing_until' => 0,
      'retry_after' => 0,
      'last_access' => 1000,
      'fail_count' => 0,
      'last_error' => NULL,
    ];
  }

}
