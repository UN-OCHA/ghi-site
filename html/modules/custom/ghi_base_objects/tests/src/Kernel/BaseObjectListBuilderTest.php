<?php

namespace Drupal\Tests\ghi_base_objects\Kernel;

use Drupal\Core\Link;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;

/**
 * Tests the admin listing fallback when views is not enabled.
 *
 * @group ghi_base_objects
 */
class BaseObjectListBuilderTest extends KernelTestBase {

  use BaseObjectTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
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
  }

  /**
   * Tests the list builder for base object types.
   */
  public function testBaseObjectTypeListBuilder() {
    /** @var \Drupal\ghi_base_objects\BaseObjectTypeListBuilder $list_builder */
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('base_object_type');

    $base_object_type = $this->createBaseObjectType();

    $header = $list_builder->buildHeader();
    $this->assertEquals('Type', $header['label']);
    $this->assertEquals('Machine name', $header['id']);

    $row = $list_builder->buildRow($base_object_type);
    $this->assertEquals($base_object_type->label(), $row['label']);
    $this->assertEquals($base_object_type->id(), $row['id']);
  }

  /**
   * Tests the list builder for base object types.
   */
  public function testBaseObjectListBuilder() {
    /** @var \Drupal\ghi_base_objects\BaseObjectTypeListBuilder $list_builder */
    $list_builder = $this->container->get('entity_type.manager')->getListBuilder('base_object');

    $base_object = $this->createBaseObject();

    $header = $list_builder->buildHeader();
    $this->assertEquals('Name', $header['name']);
    $this->assertEquals('Base object ID', $header['id']);

    $row = $list_builder->buildRow($base_object);
    $this->assertInstanceOf(Link::class, $row['name']);
    $this->assertEquals($base_object->label(), $row['name']->getText());
    $this->assertEquals($base_object->id(), $row['id']);
  }

}
