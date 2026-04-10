<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;

/**
 * Base class for api object tests.
 *
 * @group ghi_base_objects
 */
abstract class ApiBaseObjectTestBase extends UnitTestCase {

  use BaseObjectTestTrait;
  use PrivateAccessorTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ghi_base_objects_test',
    'ghi_base_objects',
  ];

  /**
   * Assert that an API object implements required methods properly.
   *
   * @param object $api_object
   *   The API object to test.
   * @param string $expected_bundle
   *   The expected bundle name.
   */
  protected function assertApiObjectBasics($api_object, string $expected_bundle): void {
    // Test basic interface methods.
    $this->assertIsString($api_object->getName());

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
      $this->assertNull($api_object->getEntity());
      $this->assertIsString($api_object->getShortName());
      $this->assertEquals($api_object->getName(), $api_object->getShortName());

      $this->assertIsString($api_object->getBundle());
      $this->assertEquals($expected_bundle, $api_object->getBundle());
    }
  }

}
