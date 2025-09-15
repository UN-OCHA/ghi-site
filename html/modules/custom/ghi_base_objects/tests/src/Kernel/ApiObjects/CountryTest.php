<?php

namespace Drupal\Tests\ghi_base_objects\Kernel\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\Country;

/**
 * Tests the Country API object.
 *
 * @group ghi_base_objects
 */
class CountryTest extends BaseObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $country_defaults = [
      'latitude' => '0.0',
      'longitude' => '0.0',
    ];

    $merged_overrides = array_merge($country_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test Country constructor and mapping.
   */
  public function testCountryConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Country',
      'latitude' => '12.345',
      'longitude' => '67.890',
    ]);

    $country = new Country($raw_data);

    $this->assertApiObjectBasics($country, 'country', [
      'id',
      'name',
      'latLng',
    ]);

    // Test specific Country methods.
    $this->assertEquals(['12.345', '67.890'], $country->getLatLng());

    $this->assertEquals('country', $country->getBundle());
    $this->assertEquals('Test Country', $country->getName());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(Country::class);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $country = new Country($raw_data);

    $cache_tags = $country->getCacheTags();
    $this->assertIsArray($cache_tags);
  }

}
