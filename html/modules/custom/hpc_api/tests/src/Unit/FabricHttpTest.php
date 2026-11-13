<?php

namespace Drupal\Tests\hpc_api\Unit;

use Drupal\hpc_api\Plugin\migrate_plus\data_fetcher\FabricHttp;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_api\Query\ImportQueryInterface;
use Drupal\migrate_plus\AuthenticationPluginManager;
use Drupal\Tests\UnitTestCase;
use GuzzleHttp\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Fabric HTTP migration data fetcher.
 */
#[CoversClass(FabricHttp::class)]
#[Group('HPC API')]
class FabricHttpTest extends UnitTestCase {

  /**
   * Test that the import query receives the migration configuration.
   */
  public function testImportQueryReceivesConfiguration(): void {
    $configuration = [
      'fabric_query' => ['plugin' => 'test'],
      'cache_base_time' => 1234,
    ];
    $source_data = [(object) ['Id' => 1]];
    $query = new TestFabricHttpImportQuery([], 'test', ['id' => 'test']);
    $query->setSourceData($source_data);

    $query_manager = $this->createMock(FabricQueryManager::class);
    $query_manager->expects($this->once())
      ->method('createInstance')
      ->with('test', $configuration + ['method' => 'GET'])
      ->willReturn($query);

    $fetcher = new TestFabricHttp($configuration, 'fabric_http', [], $this->createMock(Client::class), $this->createMock(AuthenticationPluginManager::class));
    $fetcher->setFabricQueryManager($query_manager);

    $this->assertSame($source_data, $fetcher->getSourceData());
  }

}

/**
 * Testable Fabric migration data fetcher.
 */
class TestFabricHttp extends FabricHttp {

  /**
   * Set the Fabric query manager.
   */
  public function setFabricQueryManager(FabricQueryManager $query_manager): void {
    $this->fabricQueryManager = $query_manager;
  }

}

/**
 * Import query implementation for testing the data fetcher.
 */
class TestFabricHttpImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * Source data returned by the query.
   *
   * @var object[]
   */
  private array $sourceData = [];

  /**
   * Set the source data returned by the query.
   *
   * @param object[] $source_data
   *   The source data.
   */
  public function setSourceData(array $source_data): void {
    $this->sourceData = $source_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceData(): array {
    return $this->sourceData;
  }

}
