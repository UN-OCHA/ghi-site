<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\hpc_api\ObjectStore;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Fabric query plugin base.
 */
#[CoversClass(FabricQueryBase::class)]
#[Group('HPC API')]
class FabricQueryBaseTest extends UnitTestCase {

  /**
   * Test that query plugins apply the configured cache freshness boundary.
   */
  public function testCacheBaseTimeConfiguration(): void {
    $fabric_client = $this->createMock(FabricClient::class);
    $fabric_client->expects($this->once())
      ->method('setCacheBaseTime')
      ->with(1234);

    $container = new ContainerBuilder();
    $container->set('hpc_api.fabric_client', $fabric_client);
    $container->set('hpc_api.object_store', $this->createMock(ObjectStore::class));
    $container->set('current_user', $this->createMock(AccountProxyInterface::class));
    $container->set('cache.data', $this->createMock(CacheBackendInterface::class));

    TestFabricQuery::create($container, ['cache_base_time' => 1234], 'test', ['id' => 'test']);
  }

}

/**
 * Fabric query implementation for testing the base plugin.
 */
class TestFabricQuery extends FabricQueryBase {

}
