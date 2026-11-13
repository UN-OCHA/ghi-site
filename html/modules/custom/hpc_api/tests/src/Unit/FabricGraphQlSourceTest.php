<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\hpc_api\Plugin\migrate\source\FabricGraphQl;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_api\Query\ImportQueryInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Fabric GraphQL migration source.
 */
#[CoversClass(FabricGraphQl::class)]
#[Group('HPC API')]
class FabricGraphQlSourceTest extends UnitTestCase {

  /**
   * Test the freshness boundary used by a Fabric migration.
   */
  #[DataProvider('cacheBaseTimeProvider')]
  public function testCacheBaseTime(?int $configured, int $expected): void {
    $source_configuration = $configured === NULL ? [] : ['cache_base_time' => $configured];
    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getSourceConfiguration')->willReturn($source_configuration);
    $migration->method('getIdMap')->willReturn($this->createMock(MigrateIdMapInterface::class));
    $migration->method('id')->willReturn('test');

    $time = $this->createMock(TimeInterface::class);
    $time->method('getRequestTime')->willReturn(1234);

    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->method('createInstance')->with('test')->willReturn(new TestImportFabricQuery([], 'test', ['id' => 'test']));

    $source = new FabricGraphQl([
      'fabric_query' => ['plugin' => 'test'],
      'fields' => [],
      'ids' => ['id' => ['type' => 'integer']],
    ], 'hpc_fabric_graphql', [], $migration, $query_manager, $time, $this->createMock(DataParserPluginManager::class));

    $this->assertSame($expected, $source->getCacheBaseTime());
  }

  /**
   * Provide configured and default migration freshness boundaries.
   */
  public static function cacheBaseTimeProvider(): array {
    return [
      'request time by default' => [NULL, 1234],
      'explicit migration boundary' => [5678, 5678],
    ];
  }

}

/**
 * Import query implementation for testing the migration source.
 */
class TestImportFabricQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData(): array {
    return [];
  }

}
