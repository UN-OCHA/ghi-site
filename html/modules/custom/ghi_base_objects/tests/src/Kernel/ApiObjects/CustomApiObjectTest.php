<?php

namespace Drupal\Tests\ghi_base_objects\Kernel\ApiObjects;

use Drupal\ghi_base_objects_test\ApiObjects\CustomApiObject;

/**
 * Tests the CustomApiObject API object from test module.
 *
 * @group ghi_base_objects
 */
class CustomApiObjectTest extends BaseObjectKernelTestBase {

  /**
   * Test CustomApiObject constructor and mapping.
   */
  public function testCustomApiObjectConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Custom Object',
    ]);

    $custom_object = new CustomApiObject($raw_data);

    $this->assertApiObjectBasics($custom_object, 'customapiobject', [
      'id',
      'name',
    ]);

    $this->assertEquals('customapiobject', $custom_object->getBundle());
    $this->assertEquals('Test Custom Object', $custom_object->getName());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    $this->testNullEmptyDataHandling(CustomApiObject::class);
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData();
    $custom_object = new CustomApiObject($raw_data);

    $cache_tags = $custom_object->getCacheTags();
    $this->assertIsArray($cache_tags);
  }

}
