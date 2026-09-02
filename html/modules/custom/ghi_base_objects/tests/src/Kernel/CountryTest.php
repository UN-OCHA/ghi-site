<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\ghi_geojson\Traits\GeoJsonTestTrait;

/**
 * Tests the country entity.
 *
 * @group ghi_base_objects
 */
class CountryTest extends KernelTestBase {

  use BaseObjectTestTrait;
  use GeoJsonTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'text',
    'user',
    'migrate',
    'ghi_base_objects',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('base_object');
    $this->installConfig('field');

    $this->mockGeoJsonService();
  }

  /**
   * Tests base object name methods.
   */
  public function testCountryEntity() {
    $base_object_type = $this->createBaseObjectType([
      'id' => 'country',
      'field_country_code' => ['type' => 'text', 'label' => 'Country code'],
      'field_latitude' => ['type' => 'decimal', 'label' => 'Latitude'],
      'field_longitude' => ['type' => 'decimal', 'label' => 'Longitude'],
    ]);
    /** @var \Drupal\ghi_base_objects\Entity\Country $base_object */
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'name' => 'Country name',
      'field_original_id' => 20,
      'field_country_code' => 'code',
      'field_latitude' => 0.12,
      'field_longitude' => 0.34,
    ]);
    $this->assertEquals('code', $base_object->getIso3());
    $this->assertEquals(0, $base_object->getAdminLevel());
    $this->assertNull($base_object->getPcode());
    $this->assertEquals([0.12, 0.34], $base_object->getLatLong());
    $this->assertEquals(md5(implode('_', [$base_object->id(), 'active', 'current'])), $base_object->getUuid());
    $this->assertEquals('current', $base_object->getGeoJsonVersion());
    $this->assertEquals([
      'id' => 20,
      'name' => 'Country name',
      'pcode' => NULL,
      'iso3' => 'code',
      'latLng' => [0.12, 0.34],
      'filepath' => NULL,
    ], $base_object->getGeoJsonLocationData());
  }

}
