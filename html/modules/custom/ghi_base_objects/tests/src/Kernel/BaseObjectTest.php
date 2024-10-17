<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectType;

/**
 * Tests the base object entity.
 *
 * @group ghi_base_objects
 */
class BaseObjectTest extends KernelTestBase {

  use BaseObjectTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'user',
    'ghi_base_objects',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('base_object');
    $this->installConfig('field');
  }

  /**
   * Tests base object name methods.
   */
  public function testBaseObjectName() {
    $base_object_type = $this->createBaseObjectType([
      'id' => 'plan',
    ]);
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'name' => 'base_object_name',
      'field_original_id' => 20,
    ]);
    $this->assertEquals('base_object_name', $base_object->getName());
    $base_object->setName('new name');
    $this->assertEquals('new name', $base_object->getName());
  }

  /**
   * Tests base object url method.
   */
  public function testBaseObjectUrl() {
    $base_object = $this->createBaseObject();
    $url = $base_object->toUrl();
    $this->assertInstanceOf(Url::class, $url);
    $this->assertEquals('<nolink>', $url->getRouteName());

    $edit_url = $base_object->toUrl('edit-form');
    $this->assertInstanceOf(Url::class, $edit_url);
    $this->assertEquals('/admin/content/base-objects/' . $base_object->id() . '/edit', $edit_url->toString());
  }

  /**
   * Tests base object equal method.
   */
  public function testBaseObjectEqual() {
    $base_object_type = $this->createBaseObjectType();
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'field_original_id' => 20,
    ]);

    $base_object_2 = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'field_original_id' => 20,
    ]);
    $this->assertTrue($base_object->equals($base_object_2));

    $base_object_3 = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'field_original_id' => 50,
    ]);
    $this->assertFalse($base_object->equals($base_object_3));

    $base_object_type_2 = $this->createBaseObjectType();
    $base_object_4 = $this->createBaseObject([
      'type' => $base_object_type_2->id(),
      'field_original_id' => 20,
    ]);
    $this->assertFalse($base_object->equals($base_object_4));
  }

  /**
   * Tests base object source id methods.
   */
  public function testBaseObjectSourceId() {
    $base_object_type = $this->createBaseObjectType([
      'id' => 'plan',
    ]);
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
      'name' => 'base_object_name',
      'field_original_id' => 20,
    ]);
    $this->assertEquals(20, $base_object->getSourceId());
    $this->assertEquals('plan--20', $base_object->getUniqueIdentifier());

    $base_object_type_incomplete = BaseObjectType::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'hasYear' => FALSE,
    ]);
    $base_object = $this->createBaseObject([
      'type' => $base_object_type_incomplete->id(),
      'name' => 'base_object_name',
      'field_original_id' => 20,
    ]);
    $this->assertNull($base_object->getSourceId());
  }

  /**
   * Tests base object needsYear() method.
   */
  public function testBaseObjectNeedsYear() {
    $base_object_type = $this->createBaseObjectType([
      'hasYear' => FALSE,
    ]);
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
    ]);
    $this->assertTrue($base_object->needsYear());

    $base_object_type = $this->createBaseObjectType([
      'hasYear' => TRUE,
    ]);
    $base_object = $this->createBaseObject([
      'type' => $base_object_type->id(),
    ]);
    $this->assertFalse($base_object->needsYear());
  }

  /**
   * Tests base object created timestamps.
   */
  public function testBaseObjectCreatedTime() {
    $base_object = $this->createBaseObject();
    $this->assertNotEmpty($base_object->getCreatedTime());

    $timestamp = time() - 10000;
    $base_object->setCreatedTime($timestamp);
    $this->assertEquals($timestamp, $base_object->getCreatedTime());
  }

}
