<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the LocationQuery Fabric query plugin.
 *
 * @group ghi_base_objects
 */
class LocationQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Test that large location id lookups are split into multiple queries.
   */
  public function testGetLocationsByIdBatchesLargeLookups(): void {
    $query_records = [];
    $fabric_client = $this->createMock(FabricClient::class);
    $fabric_client->expects($this->exactly(2))
      ->method('createQuery')
      ->willReturnCallback(function (string $query_name, mixed $items) use (&$query_records): FabricQuery {
        $this->assertSame('locations', $query_name);
        $this->assertSame(Location::getGraphQlItems(), $items);

        $record = (object) ['filters' => []];
        $query_records[] = $record;
        return $this->mockLocationFabricQuery($record);
      });

    $location_query = new LocationQuery([], 'location', []);
    $this->setPrivateProperty($location_query, 'fabricClient', $fabric_client);

    $locations = $location_query->getLocationsById(range(1, 1501));

    $this->assertCount(2, $query_records);
    $this->assertSame(range(1, 1500), $query_records[0]->filters['Id']);
    $this->assertSame([1501], $query_records[1]->filters['Id']);
    $this->assertSame('NOT NULL', $query_records[0]->filters['AdminLevel']);
    $this->assertCount(1501, $locations);
    $this->assertArrayHasKey(1, $locations);
    $this->assertArrayHasKey(1501, $locations);
    $this->assertContainsOnlyInstancesOf(Location::class, $locations);
  }

  /**
   * Mock a Fabric location query and record its filters.
   *
   * @param object $record
   *   An object with a filters property.
   *
   * @return \Drupal\hpc_api\Query\FabricQuery
   *   The mocked Fabric query.
   */
  private function mockLocationFabricQuery(object $record): FabricQuery {
    $query = $this->getMockBuilder(FabricQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['setFilter', 'execute'])
      ->getMock();

    $query->method('setFilter')
      ->willReturnCallback(function (string $key, mixed $value) use ($record, $query): FabricQuery {
        $record->filters[$key] = $value;
        return $query;
      });

    $query->method('execute')
      ->willReturnCallback(function () use ($record): array {
        $items = [];
        foreach ($record->filters['Id'] ?? [] as $location_id) {
          $items[$location_id] = (object) [
            'Id' => $location_id,
            'Name' => 'Location ' . $location_id,
            'AdminLevel' => 1,
            'CountryId' => 1,
            'CountryISO3' => 'TST',
            'Latitude' => 0,
            'Longitude' => 0,
            'RecordStatus' => 'active',
          ];
        }
        return $items;
      });

    return $query;
  }

}
