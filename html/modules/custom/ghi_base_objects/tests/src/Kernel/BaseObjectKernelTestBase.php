<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;

/**
 * Base class for ApiObject kernel tests.
 *
 * @group ghi_base_objects
 */
abstract class BaseObjectKernelTestBase extends KernelTestBase {

  use BaseObjectTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'migrate',
    'ghi_base_objects',
    'ghi_base_objects_test',
    'hpc_api',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('base_object');
    $this->installConfig('field');

    $this->createBaseObjectType();
  }

  /**
   * Create mock raw data for API objects.
   *
   * @param array $data_overrides
   *   Optional data to override defaults.
   *
   * @return object
   *   Mock raw data object.
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $default_data = [
      'Id' => rand(1, 1000),
      'Name' => $this->randomString(),
    ];

    $merged_data = array_merge($default_data, $data_overrides);
    return (object) $merged_data;
  }

  /**
   * Assert that an API object implements required methods properly.
   *
   * @param object $api_object
   *   The API object to test.
   * @param string $expected_bundle
   *   The expected bundle name.
   */
  protected function assertApiObjectBasics($api_object, string $expected_bundle): void {
    // Test ID and raw data access.
    $this->assertIsInt($api_object->id());
    $this->assertIsObject($api_object->getRawData());

    // Test that toArray returns an array.
    $this->assertIsArray($api_object->toArray());

    // Test cache methods.
    $this->assertIsArray($api_object->getCacheTags());
    $this->assertIsArray($api_object->getCacheContexts());
    $this->assertIsInt($api_object->getCacheMaxAge());

    if ($api_object instanceof BaseObject) {
      $this->assertIsString($api_object->getName());
      $this->assertNull($api_object->getEntity());
      $this->assertIsString($api_object->getShortName());
      $this->assertEquals($api_object->getName(), $api_object->getShortName());

      $this->assertIsString($api_object->getBundle());
      $this->assertEquals($expected_bundle, $api_object->getBundle());
    }
  }

}
