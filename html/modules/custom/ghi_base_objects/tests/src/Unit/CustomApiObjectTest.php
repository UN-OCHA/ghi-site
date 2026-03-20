<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\ghi_base_objects_test\ApiObjects\CustomApiObject;

/**
 * Tests the api base object class.
 *
 * @group ghi_base_objects
 */
class CustomApiObjectTest extends ApiBaseObjectTestBase {

  /**
   * Test common methods of ApiBaseObject classes.
   */
  public function testApiBaseObject() {
    $raw_data = (object) [
      'Id' => 1,
      'Name' => 'Custom object 1',
    ];
    $custom_object = new CustomApiObject($raw_data);
    $this->assertEquals($raw_data, $custom_object->getRawData());
    $this->assertEquals($raw_data->Id, $custom_object->id());
    $this->assertEquals('customapiobject', $custom_object->getBundle());
    $this->assertEquals($raw_data->Name, $custom_object->getName());

    $custom_object->setCacheTags(['one', 'two']);
    $this->assertEquals(['one', 'two'], $custom_object->getCacheTags());

    $this->assertEquals(['data' => serialize($raw_data)], $custom_object->__serialize());

    $raw_data = (object) [
      'Id' => 2,
      'Name' => 'Custom object 2',
    ];
    $custom_object->__unserialize(['data' => serialize($raw_data)]);
    $this->assertEquals($raw_data, $custom_object->getRawData());
    $this->assertEquals($raw_data->Id, $custom_object->id());
    $this->assertEquals($raw_data->Name, $custom_object->getName());
  }

}
