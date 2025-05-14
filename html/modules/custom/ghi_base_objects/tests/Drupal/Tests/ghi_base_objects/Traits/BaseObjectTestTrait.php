<?php

namespace Drupal\Tests\ghi_base_objects\Traits;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectType;
use Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;

/**
 * Provides methods to create base objects in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait BaseObjectTestTrait {

  use FieldTestTrait;
  use EntityReferenceFieldCreationTrait;

  /**
   * Create a base object type.
   *
   * @param mixed[] $values
   *   (optional) Additional key-value pairs for the base object type entity:
   *   - id: The ID of the base object type. If none is provided, a random value
   *     will be used.
   *   - label: The human-readable label of the base object type. If none is
   *     provided, a random value will be used.
   *   - field_year: When not empty, this will create a year field with the
   *     provided label.
   *   - field_plan: When not empty, this will create a plan field with the
   *     provided label.
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
      'hasYear' => !empty($values['field_year']),
      'field_year' => NULL,
      'field_plan' => NULL,
    ];
    $base_object_type = BaseObjectType::load($values['id']) ?: BaseObjectType::create($values);
    if ($base_object_type->isNew()) {
      $this->assertSame(SAVED_NEW, $base_object_type->save());
      $this->assertInstanceOf(BaseObjectTypeInterface::class, $base_object_type);
      $this->createField('base_object', $base_object_type->id(), 'integer', 'field_original_id', 'Source id');
      if (!empty($values['field_year'])) {
        $this->createField('base_object', $base_object_type->id(), 'integer', 'field_year', $values['field_year']);
      }
      if (!empty($values['field_plan'])) {
        $this->createEntityReferenceField('base_object', $base_object_type->id(), 'field_plan', $values['field_plan'], 'base_object', 'default', [
          'target_bundles' => [
            'plan' => 'plan',
          ],
        ]);
      }
    }
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
