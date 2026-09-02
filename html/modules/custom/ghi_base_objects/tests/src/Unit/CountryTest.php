<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\Tests\ghi_geojson\Traits\GeoJsonTestTrait;

/**
 * Tests the Country API object.
 *
 * @group ghi_base_objects
 */
class CountryTest extends ApiBaseObjectTestBase {

  use GeoJsonTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->mockGeoJsonService();
  }

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $location_defaults = [
      'Id' => 1,
      'Name' => 'Test Country',
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
   * Test Country constructor and mapping.
   */
  public function testCountryConstructorAndMapping(): void {
    $data = (object) [
      'Id' => 123,
      'Name' => 'Test Country',
      'Latitude' => '12.345',
      'Longitude' => '67.890',
      'Pcode' => 'ABC',
      'ISO3' => 'TC',
      'AdminLevel' => 0,
      'ActiveUntil' => '1434326400000',
      'RecordStatus' => 'Active',
    ];
    $country = new Country($data);

    // Test specific Country methods.
    $this->assertEquals(['12.345', '67.890'], $country->getLatLng());

    $this->assertEquals('country', $country->getBundle());
    $this->assertEquals(123, $country->id());
    $this->assertEquals(md5(implode('_', [123, 'active', '1434326400'])), $country->getUuid());
    $this->assertEquals('Test Country', $country->getName());
    $this->assertEquals('TC', $country->getIso3());
    $this->assertEquals('ABC', $country->getPcode());
    $this->assertEquals(0, $country->getAdminLevel());
    $this->assertEquals('current', $country->getGeoJsonVersion());

    // Test as an expired country.
    $data->RecordStatus = 'Expired';
    $country = new Country($data);
    $this->assertEquals(2015, $country->getGeoJsonVersion());
  }

  /**
   * Test location UUID generation.
   */
  public function testLocationUuidGeneration(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'RecordStatus' => 'active',
    ]);
    $country = new Country($raw_data);

    $uuid = $country->getUuid();
    $this->assertIsString($uuid);
    $this->assertNotEmpty($uuid);

    // Test that the same data produces the same UUID.
    $country2 = new Country($raw_data);
    $this->assertEquals($uuid, $country2->getUuid());

    // Also test that the uuid is added to the cache tags.
    $cache_tags = $country->getCacheTags();
    $this->assertIsArray($cache_tags);
    $this->assertContains($country->getUuid(), $cache_tags);
  }

  /**
   * Test some geojson methods.
   */
  public function testGeoJson(): void {
    $raw_data = $this->createMockRawData([
      'RecordStatus' => 'active',
      'ActiveUntil' => '1434326400000',
    ]);
    $country = new Country($raw_data);
    $this->assertTrue($country->hasGeoJsonFile());
    $this->assertEquals('current', $country->getGeoJsonVersion());

    $array = $country->toArray();
    $geojson = $country->getGeoJsonLocationData();
    $this->assertIsArray($array);
    $this->assertIsArray($geojson);
    $this->assertEquals(array_keys($array + ['filepath' => 'something']), array_keys($geojson));

    $raw_data = $this->createMockRawData([
      'RecordStatus' => 'expired',
      'ActiveUntil' => '1434326400000',
    ]);
    $country = new Country($raw_data);
    $this->assertTrue($country->hasGeoJsonFile());
    $this->assertEquals('2015', $country->getGeoJsonVersion());
  }

}
