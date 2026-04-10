<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\FieldHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @covers Drupal\hpc_common\Helpers\FieldHelper
 */
class FieldHelperTest extends UnitTestCase {

  /**
   * Test getBooleanFieldOptions returns NULL for non-existent field.
   *
   * @group FieldHelper
   */
  public function testGetBooleanFieldOptionsNonExistentField() {
    $container = $this->createMockContainerWithEntityFieldManager([]);
    \Drupal::setContainer($container);

    $result = FieldHelper::getBooleanFieldOptions('node', 'article', 'non_existent_field');
    $this->assertNull($result);
  }

  /**
   * Test getBooleanFieldOptions returns NULL for non-boolean field.
   *
   * @group FieldHelper
   */
  public function testGetBooleanFieldOptionsNonBooleanField() {
    $field_definition = $this->createMockFieldDefinition('string');

    $container = $this->createMockContainerWithEntityFieldManager([
      'field_test' => $field_definition,
    ]);
    \Drupal::setContainer($container);

    $result = FieldHelper::getBooleanFieldOptions('node', 'article', 'field_test');
    $this->assertNull($result);
  }

  /**
   * Test getBooleanFieldOptions returns options for boolean field.
   *
   * @group FieldHelper
   */
  public function testGetBooleanFieldOptionsValidBooleanField() {
    $field_definition = $this->createMockFieldDefinition('boolean', [
      'on_label' => 'Yes',
      'off_label' => 'No',
    ]);

    $container = $this->createMockContainerWithEntityFieldManager([
      'field_active' => $field_definition,
    ]);
    \Drupal::setContainer($container);

    $result = FieldHelper::getBooleanFieldOptions('node', 'article', 'field_active');
    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertSame('Yes', $result[TRUE]);
    $this->assertSame('No', $result[FALSE]);
  }

  /**
   * Create a mock container with EntityFieldManager.
   */
  private function createMockContainerWithEntityFieldManager(array $fields) {
    $field_definitions = [];
    foreach ($fields as $name => $definition) {
      $field_definitions[$name] = $definition;
    }

    $entity_field_manager = $this->createMock(EntityFieldManagerInterface::class);
    $entity_field_manager->method('getFieldDefinitions')
      ->willReturn($field_definitions);

    $container = $this->createMock(ContainerInterface::class);
    $container->method('get')
      ->willReturnMap([
        ['entity_field.manager', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $entity_field_manager],
      ]);

    return $container;
  }

  /**
   * Create a mock field definition.
   */
  private function createMockFieldDefinition($type, array $settings = []) {
    $storage_definition = $this->createMock(FieldStorageDefinitionInterface::class);
    $storage_definition->method('getType')->willReturn($type);

    $field_definition = $this->createMock(FieldDefinitionInterface::class);
    $field_definition->method('getFieldStorageDefinition')->willReturn($storage_definition);
    $field_definition->method('getSettings')->willReturn($settings);

    return $field_definition;
  }

}
