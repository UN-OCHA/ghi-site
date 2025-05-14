<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\ghi_base_objects_test\ApiObjects\CustomApiObject;

/**
 * Tests the api base object class.
 *
 * @group ghi_base_objects
 */
class ApiBaseObjectTest extends UnitTestCase {

  use BaseObjectTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ghi_base_objects_test',
    'ghi_base_objects',
  ];

  /**
   * Test common methods of ApiBaseObject classes.
   */
  public function testApiBaseObject() {
    $raw_data = (object) [
      'id' => 1,
      'name' => 'Custom object 1',
    ];
    $custom_object = new CustomApiObject($raw_data);
    $this->assertEquals($raw_data, $custom_object->getRawData());
    $this->assertEquals($raw_data->id, $custom_object->id());
    $this->assertEquals('customapiobject', $custom_object->getBundle());
    $this->assertEquals($raw_data->name, $custom_object->getName());

    $custom_object->setCacheTags(['one', 'two']);
    $this->assertEquals(['one', 'two'], $custom_object->getCacheTags());

    $this->assertEquals(['data' => serialize($raw_data)], $custom_object->__serialize());

    $raw_data = (object) [
      'id' => 2,
      'name' => 'Custom object 2',
    ];
    $custom_object->__unserialize(['data' => serialize($raw_data)]);
    $this->assertEquals($raw_data, $custom_object->getRawData());
    $this->assertEquals($raw_data->id, $custom_object->id());
    $this->assertEquals($raw_data->name, $custom_object->getName());
  }

}
