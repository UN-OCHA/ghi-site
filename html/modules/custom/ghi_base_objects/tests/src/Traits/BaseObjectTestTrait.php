<?php

namespace Drupal\Tests\ghi_base_objects\Traits;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectType;
use Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface;

/**
 * Provides methods to create base objects in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait BaseObjectTestTrait {

  use FieldTestTrait;

  /**
   * Create a base object type.
   *
   * @param mixed[] $values
   *   (optional) Additional values for the base object type entity:
   *   - id: The ID of the base object type. If none is provided, a random value
   *     will be used.
   *   - label: The human-readable label of the base object type. If none is
   *     provided, a random value will be used.
   *   - hasYear: Whether the base object type handles years already.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   *   A base object type.
   *
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectType
   */
  protected function createBaseObjectType(array $values = []) {
    $values += [
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'hasYear' => FALSE,
    ];
    $base_object_type = BaseObjectType::create($values);
    $this->assertSame(SAVED_NEW, $base_object_type->save());
    $this->assertInstanceOf(BaseObjectTypeInterface::class, $base_object_type);
    $this->createField('base_object', $base_object_type->id(), 'integer', 'field_original_id', 'Source id');
    return $base_object_type;
  }

  /**
   * Create a base object.
   *
   * @param mixed[] $values
   *   (optional) Additional values for the base object entity:
   *   - name: The human-readable label of the base object type. If none is
   *     provided, a random value will be used.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   A base object type.
   *
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   * @see \Drupal\ghi_base_objects\Entity\BaseObjectType
   */
  protected function createBaseObject(array $values = []) {
    $values += [
      'type' => $this->randomString(),
      'name' => $this->randomString(),
      'field_original_id' => rand(1, 100),
      'field_content_space' => rand(1, 100),
    ];
    $base_object = BaseObject::create($values);
    $this->assertSame(SAVED_NEW, $base_object->save());
    $this->assertInstanceOf(BaseObjectInterface::class, $base_object);
    return $base_object;
  }

}
