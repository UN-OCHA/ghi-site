<?php

namespace Drupal\Tests\hpc_remote_data_cache\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\hpc_remote_data_cache\RemoteDataCacheItem;

/**
 * @covers \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
 *
 * @group HPC Remote Data Cache
 */
class RemoteDataCacheItemTest extends UnitTestCase {

  /**
   * Test item states.
   *
   * @param int $request_time
   *   The request timestamp.
   * @param string $expected_state
   *   The expected item state.
   *
   * @dataProvider stateProvider
   */
  public function testStates(int $request_time, string $expected_state): void {
    $item = $this->createItem($request_time);
    $this->assertSame($expected_state, $item->getState());
  }

  /**
   * Data provider for testStates().
   *
   * @return array
   *   The test cases.
   */
  public function stateProvider(): array {
    return [
      [1000, RemoteDataCacheItem::STATE_FRESH],
      [1200, RemoteDataCacheItem::STATE_FRESH],
      [1201, RemoteDataCacheItem::STATE_STALE],
      [1600, RemoteDataCacheItem::STATE_STALE],
      [1601, RemoteDataCacheItem::STATE_EXPIRED],
    ];
  }

  /**
   * Create a test item.
   *
   * @param int $request_time
   *   The request timestamp.
   *
   * @return \Drupal\hpc_remote_data_cache\RemoteDataCacheItem
   *   The test item.
   */
  private function createItem(int $request_time): RemoteDataCacheItem {
    return new RemoteDataCacheItem(
      'cid',
      'test',
      'https://example.test',
      '{}',
      [],
      (object) [],
      100,
      100,
      100,
      1200,
      1600,
      FALSE,
      0,
      0,
      100,
      0,
      NULL,
      100,
      $request_time,
    );
  }

}
