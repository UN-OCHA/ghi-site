<?php

namespace Drupal\hpc_common\Helpers;

/**
 * Helper class for fields.
 */
class FieldHelper {

  /**
   * Get the options for a boolean field.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   An array of strings with 2 items, one for the "on" label and one for the
   *   "off" label.
   */
  public static function getBooleanFieldOptions($entity_type, $bundle, $field_name) {
    /** @var \Drupal\Core\Entity\EntityFieldManager $entity_field_manager */
    $entity_field_manager = \Drupal::service('entity_field.manager');
    $field_definitions = $entity_field_manager->getFieldDefinitions($entity_type, $bundle);
    if (!array_key_exists($field_name, $field_definitions)) {
      return NULL;
    }
    if ($field_definitions[$field_name]->getFieldStorageDefinition()->getType() != 'boolean') {
      return NULL;
    }
    $settings = $field_definitions[$field_name]->getSettings();
    return [
      TRUE => $settings['on_label'],
      FALSE => $settings['off_label'],
    ];
  }

}
