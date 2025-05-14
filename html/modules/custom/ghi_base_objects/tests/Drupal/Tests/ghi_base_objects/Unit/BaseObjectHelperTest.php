<?php

namespace Drupal\Tests\ghi_base_objects\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Tests\ghi_base_objects\Traits\BaseObjectTestTrait;
use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\node\Entity\Node;
use Prophecy\Prophecy\MethodProphecy;

/**
 * Tests the base object entity.
 *
 * @group ghi_base_objects
 */
class BaseObjectHelperTest extends UnitTestCase {

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
   * Test extracting the field name for a base object reference.
   */
  public function testGetBaseObjectFieldName() {
    $text_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $text_field_definition->getType()->willReturn('text');
    $reference_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $reference_field_definition->getType()->willReturn('entity_reference');
    $reference_field_definition->getSettings()->willReturn([
      'target_type' => 'base_object',
    ]);
    $reference_field_definition->getName()->willReturn('field_reference');
    $other_reference_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $other_reference_field_definition->getType()->willReturn('entity_reference');
    $other_reference_field_definition->getSettings()->willReturn([
      'target_type' => 'taxonomy_term',
    ]);

    // First cycle.
    $definitions = [
      'field_1' => $text_field_definition->reveal(),
      'field_2' => $text_field_definition->reveal(),
      'field_term' => $other_reference_field_definition->reveal(),
      'field_reference' => $reference_field_definition->reveal(),
      'field_3' => $text_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);
    $field_name = BaseObjectHelper::getBaseObjectFieldName($node->reveal());
    $this->assertEquals('field_reference', $field_name);

    // Next cycle.
    $definitions = [
      'field_reference' => $reference_field_definition->reveal(),
      'field_1' => $text_field_definition->reveal(),
      'field_2' => $text_field_definition->reveal(),
      'field_term' => $other_reference_field_definition->reveal(),
      'field_3' => $text_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);
    $field_name = BaseObjectHelper::getBaseObjectFieldName($node->reveal());
    $this->assertEquals('field_reference', $field_name);

    // Next cycle.
    $definitions = [
      'field_1' => $text_field_definition->reveal(),
      'field_2' => $text_field_definition->reveal(),
      'field_term' => $other_reference_field_definition->reveal(),
      'field_3' => $text_field_definition->reveal(),
      'field_reference' => $reference_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);
    $field_name = BaseObjectHelper::getBaseObjectFieldName($node->reveal());
    $this->assertEquals('field_reference', $field_name);

    // Next cycle.
    $definitions = [
      'field_1' => $text_field_definition->reveal(),
      'field_2' => $text_field_definition->reveal(),
      'field_term' => $other_reference_field_definition->reveal(),
      'field_3' => $text_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);
    $field_name = BaseObjectHelper::getBaseObjectFieldName($node->reveal());
    $this->assertEquals(NULL, $field_name);
  }

  /**
   * Test loading base objects from their original ids.
   */
  public function testGetBaseObjectsFromOriginalIds() {
    // Mock entity storage.
    $entity_storage = $this->createMock(ContentEntityStorageInterface::class);

    // Mock entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([20]);

    $entity = $this->prophesize(BaseObject::class);
    $entity->hasField('field_original_id')->willReturn(TRUE);
    $entity->get('field_original_id')->willReturn($field->reveal());
    $entity->bundle()->willReturn('plan');
    $entity->id()->willReturn(1);
    $entity->getSourceId()->willReturn(20);

    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->willReturn([$entity->reveal()]);

    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with('base_object')
      ->willReturn($entity_storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $this->assertNull(BaseObjectHelper::getBaseObjectsFromOriginalIds([], 'plan'));
    $this->assertNull(BaseObjectHelper::getBaseObjectsFromOriginalIds([20], NULL));
    $base_objects = BaseObjectHelper::getBaseObjectsFromOriginalIds([20], 'plan');
    $this->assertNotEmpty($base_objects);
    $this->assertArrayHasKey(20, $base_objects);
    $this->assertEquals($entity->reveal(), $base_objects[20]);

    $base_objects = BaseObjectHelper::getBaseObjectsFromOriginalIds([50], 'plan');
    $this->assertEmpty($base_objects);

    $base_objects = BaseObjectHelper::getBaseObjectsFromOriginalIds([20], 'country');
    $this->assertEmpty($base_objects);

    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId(20, 'plan');
    $this->assertInstanceOf(BaseObjectInterface::class, $base_object);

    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId(50, 'plan');
    $this->assertEmpty($base_object);

    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId(20, 'country');
    $this->assertEmpty($base_object);
  }

  /**
   * Test loading base objects from their original ids.
   */
  public function testGetBaseObjectsFromOriginalIdsEmpty() {
    // Mock entity storage.
    $entity_storage = $this->createMock(ContentEntityStorageInterface::class);

    // Mock entity type manager.
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $field = $this->prophesize(FieldItemListInterface::class);
    $field->getValue()->willReturn([20]);

    $entity = $this->prophesize(BaseObject::class);
    $entity->hasField('field_original_id')->willReturn(TRUE);
    $entity->get('field_original_id')->willReturn($field->reveal());
    $entity->bundle()->willReturn('plan');
    $entity->id()->willReturn(1);
    $entity->getSourceId()->willReturn(20);

    $entity_storage->expects($this->any())
      ->method('loadByProperties')
      ->willReturn([]);

    $entity_type_manager->expects($this->any())
      ->method('getStorage')
      ->with('base_object')
      ->willReturn($entity_storage);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $entity_type_manager);
    \Drupal::setContainer($container);

    $base_object = BaseObjectHelper::getBaseObjectFromOriginalId(20, 'country');
    $this->assertEmpty($base_object);
  }

  /**
   * Test loading base objects from a node.
   */
  public function testGetBaseObjectsFromNode() {
    $base_object = $this->prophesize(BaseObject::class);
    $base_object->bundle()->willReturn('plan');
    $base_object->hasField('field_plan')->willReturn(FALSE);

    $reference_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $reference_field_definition->getType()->willReturn('entity_reference');
    $reference_field_definition->getSettings()->willReturn([
      'target_type' => 'base_object',
    ]);
    $reference_field_definition->getName()->willReturn('field_reference');

    // First cycle.
    $definitions = [
      'field_reference' => $reference_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);

    $reference_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference_field->referencedEntities()->willReturn([$base_object->reveal()]);
    $magic_method = new MethodProphecy($reference_field, '__get', ['entity']);
    $magic_method->willReturn($base_object->reveal());
    $reference_field->addMethodProphecy($magic_method);
    $node->get('field_reference')->willReturn($reference_field->reveal());

    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($node->reveal());
    $this->assertNotEmpty($base_objects);

    $result = BaseObjectHelper::getBaseObjectFromNode($node->reveal(), 'plan');
    $this->assertNotEmpty($result);

    $result = BaseObjectHelper::getBaseObjectFromNode($node->reveal());
    $this->assertNotEmpty($result);
  }

  /**
   * Test loading when no base objects are on the node.
   */
  public function testGetBaseObjectsFromNodeEmpty() {
    $reference_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $reference_field_definition->getType()->willReturn('entity_reference');
    $reference_field_definition->getSettings()->willReturn([
      'target_type' => 'base_object',
    ]);
    $reference_field_definition->getName()->willReturn('field_reference');

    // First cycle.
    $definitions = [
      'field_reference' => $reference_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);

    $reference_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference_field->referencedEntities()->willReturn([]);
    $node->get('field_reference')->willReturn($reference_field->reveal());
    $node->hasField('field_entity_reference')->willReturn(FALSE);

    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($node->reveal());
    $this->assertNull($base_objects);
  }

  /**
   * Test loading when base objects are on the parent node.
   */
  public function testGetBaseObjectsFromNodeChained() {
    $base_object = $this->prophesize(BaseObject::class);
    $base_object->bundle()->willReturn('plan');
    $base_object->hasField('field_plan')->willReturn(FALSE);

    $reference_field_definition = $this->prophesize(FieldDefinitionInterface::class);
    $reference_field_definition->getType()->willReturn('entity_reference');
    $reference_field_definition->getSettings()->willReturn([
      'target_type' => 'base_object',
    ]);
    $reference_field_definition->getName()->willReturn('field_reference');

    // First cycle.
    $definitions = [
      'field_reference' => $reference_field_definition->reveal(),
    ];
    $node = $this->prophesize(Node::class);
    $node->getFieldDefinitions()->willReturn($definitions);

    $reference_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference_field->referencedEntities()->willReturn([]);
    $magic_method = new MethodProphecy($reference_field, '__get', ['entity']);
    $magic_method->willReturn(NULL);
    $reference_field->addMethodProphecy($magic_method);
    $node->get('field_reference')->willReturn($reference_field->reveal());

    $reference_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference_field->referencedEntities()->willReturn([$base_object->reveal()]);
    $magic_method = new MethodProphecy($reference_field, '__get', ['entity']);
    $magic_method->willReturn($base_object->reveal());
    $reference_field->addMethodProphecy($magic_method);
    $parent_node = $this->prophesize(Node::class);
    $parent_node->getFieldDefinitions()->willReturn($definitions);
    $parent_node->get('field_reference')->willReturn($reference_field->reveal());

    $reference_field = $this->prophesize(EntityReferenceFieldItemListInterface::class);
    $reference_field->referencedEntities()->willReturn([$parent_node->reveal()]);
    $magic_method = new MethodProphecy($reference_field, '__get', ['entity']);
    $magic_method->willReturn($parent_node->reveal());
    $reference_field->addMethodProphecy($magic_method);

    $node->hasField('field_entity_reference')->willReturn(TRUE);
    $node->get('field_entity_reference')->willReturn($reference_field->reveal());

    $base_objects = BaseObjectHelper::getBaseObjectsFromNode($node->reveal());
    $this->assertNotNull($base_objects);

    $base_objects = BaseObjectHelper::getBaseObjectFromNode($node->reveal());
    $this->assertNotEmpty($base_objects);
  }

}
