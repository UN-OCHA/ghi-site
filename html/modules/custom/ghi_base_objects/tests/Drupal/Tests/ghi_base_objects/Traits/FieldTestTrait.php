<?php

namespace Drupal\Tests\ghi_base_objects\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Provides methods for working with fields in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait FieldTestTrait {

  /**
   * Creates the testing fields.
   */
  protected function createField($entity_type, $bundle, $field_type, $field_name, $field_label) {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      $field_storage = FieldStorageConfig::create([
        'type' => $field_type,
        'entity_type' => $entity_type,
        'field_name' => $field_name,
      ]);
      $field_storage->save();
    }
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'label' => $field_label,
    ])->save();
  }

  /**
   * Check if a node bundle has a field.
   *
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   Returns a TRUE if the entity type has the field.
   */
  private function bundleHasField(string $bundle, string $field_name) {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    return array_key_exists($field_name, $fields);
  }

}
