<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Prophecy\Argument;

/**
 * Tests the Location API object.
 *
 * @group ghi_base_objects
 */
class LocationTest extends ApiBaseObjectTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the geojson service to avoid dependency on ghi_geojson module.
    $geojson_mock = $this->createMock('\Drupal\ghi_geojson\GeoJson');
    $geojson_mock->method('getGeoJsonSourceFilePath')->willReturn('/test/path/location.geojson');
    $geojson_mock->method('getGeoJsonPublicFilePath')->willReturn('public://test/location.geojson');

    $url = $this->prophesize(Url::class);
    $file_url_generator = $this->prophesize(FileUrlGeneratorInterface::class);
    $file_url_generator->generate(Argument::any())->willReturn($url->reveal());

    $container = new ContainerBuilder();
    $container->set('geojson', $geojson_mock);
    $container->set('file_url_generator', $file_url_generator->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $location_defaults = [
      'Id' => 1,
      'Name' => 'Test Location',
      'Latitude' => '0.0',
      'Longitude' => '0.0',
      'AdminLevel' => 1,
      'Pcode' => 'TEST001',
      'ISO3' => 'TST',
      'CountryId' => 1,
      'RecordStatus' => 'active',
      'ActiveUntil' => '1434326400000',
    ];

    return (object) array_merge($location_defaults, $data_overrides);
  }

  /**
   * Test Location constructor and mapping.
   */
  public function testLocationConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'Name' => 'Test Location',
      'AdminLevel' => 1,
      'Pcode' => 'TEST001',
      'ISO3' => NULL,
      'Latitude' => '12.345',
      'Longitude' => '67.890',
      'RecordStatus' => 'active',
      'ActiveUntil' => '1434326400000',
    ]);

    $location = new Location($raw_data);

    // Test specific Location methods.
    $this->assertEquals('12.345', $location->getLatitude());
    $this->assertEquals('67.890', $location->getLongitude());
    $this->assertEquals(['12.345', '67.890'], $location->getLatLng());
    $this->assertEquals(1, $location->getAdminLevel());
    $this->assertEquals('TEST001', $location->getPcode());
    $this->assertNull($location->getIso3());
    $this->assertFalse($location->isCountry());

    $this->assertEquals('location', $location->getBundle());
    $this->assertEquals('Test Location', $location->getName());
  }

  /**
   * Test location UUID generation.
   */
  public function testLocationUuidGeneration(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'RecordStatus' => 'active',
    ]);
    $location = new Location($raw_data);

    $uuid = $location->getUuid();
    $this->assertIsString($uuid);
    $this->assertNotEmpty($uuid);

    // Test that the same data produces the same UUID.
    $location2 = new Location($raw_data);
    $this->assertEquals($uuid, $location2->getUuid());

    // Also test that the uuid is added to the cache tags.
    $cache_tags = $location->getCacheTags();
    $this->assertIsArray($cache_tags);
    $this->assertContains($location->getUuid(), $cache_tags);
  }

  /**
   * Test some geojson methods.
   */
  public function testGeoJson(): void {
    $raw_data = $this->createMockRawData([
      'RecordStatus' => 'active',
      'ActiveUntil' => '1434326400000',
    ]);
    $location = new Location($raw_data);
    $this->assertTrue($location->hasGeoJsonFile());
    $this->assertEquals('current', $location->getGeoJsonVersion());

    $array = $location->toArray();
    $geojson = $location->getGeoJsonLocationData();
    $this->assertIsArray($array);
    $this->assertIsArray($geojson);
    $this->assertEquals(array_keys($array + ['filepath' => 'something']), array_keys($geojson));

    $raw_data = $this->createMockRawData([
      'RecordStatus' => 'expired',
      'ActiveUntil' => '1434326400000',
    ]);
    $location = new Location($raw_data);
    $this->assertTrue($location->hasGeoJsonFile());
    $this->assertEquals('2015', $location->getGeoJsonVersion());
  }

}
