<?php

namespace Drupal\Tests\ghi_base_objects\Kernel\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\Location;

/**
 * Tests the Location API object.
 *
 * @group ghi_base_objects
 */
class LocationTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'migrate',
    'ghi_base_objects',
    'hpc_api',
    'hpc_common',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the geojson service to avoid dependency on ghi_geojson module.
    $geojson_mock = $this->createMock('\Drupal\ghi_geojson\GeoJson');
    $geojson_mock->method('getGeoJsonSourceFilePath')->willReturn('/test/path/location.geojson');
    $geojson_mock->method('getGeoJsonPublicFilePath')->willReturn('public://test/location.geojson');

    $this->container->set('geojson', $geojson_mock);
  }

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $location_defaults = [
      'latitude' => '0.0',
      'longitude' => '0.0',
      'adminLevel' => 1,
      'pcode' => 'TEST001',
      'iso3' => 'TST',
      'parentId' => NULL,
      'status' => 'active',
      'validOn' => '1434326400000',
      'externalId' => 'EXT001',
    ];

    $merged_overrides = array_merge($location_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test Location constructor and mapping.
   */
  public function testLocationConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Location',
      'adminLevel' => 1,
      'pcode' => 'TEST001',
      'iso3' => NULL,
      'latitude' => '12.345',
      'longitude' => '67.890',
      // Keep this null on purpose for testing. Prevents issues trying to do a
      // locations query api call.
      'parentId' => NULL,
      'status' => 'active',
      'validOn' => '1434326400000',
    ]);

    $location = new Location($raw_data);

    $this->assertApiObjectBasics($location, 'location', [
      'location_id',
      'location_name',
      'admin_level',
      'pcode',
      'iso3',
      'latLng',
      'parent_id',
      'status',
      'valid_on',
    ]);

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
   * Test getChildren method functionality.
   */
  public function testGetChildren(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'status' => 'active',
      'children' => [],
    ]);
    $location = new Location($raw_data);
    $this->assertIsArray($location->getChildren());
    $this->assertCount(0, $location->getChildren());

    $raw_data = $this->createMockRawData([
      'id' => 123,
      'status' => 'active',
      'children' => ['location1', 'location2'],
    ]);
    $location = new Location($raw_data);
    $this->assertIsArray($location->getChildren());
    $this->assertCount(2, $location->getChildren());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(Location::class);

    // Test with empty name (should fall back to admin area).
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => '',
      'externalId' => 'EXT123',
    ]);
    $location = new Location($raw_data);
    $this->assertEquals('Admin area EXT123', $location->getName());
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'status' => 'active',
      'validOn' => '1434326400000',
    ]);
    $location = new Location($raw_data);

    $cache_tags = $location->getCacheTags();
    $this->assertIsArray($cache_tags);
    $this->assertContains($location->getUuid(), $cache_tags);
  }

  /**
   * Test country-specific functionality.
   */
  public function testCountryFunctionality(): void {
    // Test country (admin level 0).
    $country_data = $this->createMockRawData([
      'id' => 123,
      'adminLevel' => 0,
      'iso3' => 'USA',
    ]);
    $country_location = new Location($country_data);

    $this->assertTrue($country_location->isCountry());
    $this->assertEquals('USA', $country_location->getIso3());

    // Test country (admin level 0).
    $location_data = $this->createMockRawData([
      'adminLevel' => 1,
    ]);
    $child_location = new Location($location_data);

    $this->assertNull($child_location->getParentCountry());
    $child_location->setParentCountry($country_location);
    $this->assertEquals($country_location, $child_location->getParentCountry());
  }

  /**
   * Test location UUID generation.
   */
  public function testLocationUuidGeneration(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'status' => 'active',
    ]);
    $location = new Location($raw_data);

    $uuid = $location->getUuid();
    $this->assertIsString($uuid);
    $this->assertNotEmpty($uuid);

    // Test that the same data produces the same UUID.
    $location2 = new Location($raw_data);
    $this->assertEquals($uuid, $location2->getUuid());
  }

  /**
   * Test some geojson methods.
   */
  public function testGeoJson(): void {
    $raw_data = $this->createMockRawData([
      'status' => 'active',
      'validOn' => '1434326400000',
    ]);
    $location = new Location($raw_data);
    $this->assertTrue($location->hasGeoJsonFile());
    $this->assertEquals('current', $location->getGeoJsonVersion());

    $raw_data = $this->createMockRawData([
      'status' => 'expired',
      'validOn' => '1434326400000',
    ]);
    $location = new Location($raw_data);
    $this->assertTrue($location->hasGeoJsonFile());
    $this->assertEquals('2015', $location->getGeoJsonVersion());
  }

}
