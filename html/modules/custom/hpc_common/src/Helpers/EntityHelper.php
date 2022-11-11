<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Helper class for entities.
 */
class EntityHelper {

  /**
   * Simple helper function to retrieve the number of items of a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string $field_name
   *   The field name for which the data should be retrieved.
   *
   * @return int
   *   The number of items the field has.
   */
  public static function getFieldCount(ContentEntityInterface $entity, $field_name) {
    $items = $entity->get($field_name)->getValue();
    return is_array($items) ? count($items) : 0;
  }

  /**
   * Simple helper function to retrieve the raw data of a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   * @param string $field_name
   *   The field name for which the data should be retrieved.
   * @param int $delta
   *   Optionally: a delta for a specific item of the field.
   * @param string $property
   *   Optionally: The property name inside each item.
   *
   * @return mixed
   *   The raw data for the given field, optionally stripped down by delta and
   *   property.
   */
  public static function getFieldData(ContentEntityInterface $entity, $field_name, $delta = NULL, $property = NULL) {
    if (!$entity->hasField($field_name)) {
      return NULL;
    }
    $items = $entity->get($field_name)->getValue();
    if (empty($items)) {
      return NULL;
    }
    $item = $delta !== NULL ? ($items[$delta] ?? NULL) : ($items[0] ?? NULL);
    return $property !== NULL && isset($item[$property]) ? $item[$property] : $item;
  }

  /**
   * Simple helper function to retrieve the raw data of a fields property.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity.
   * @param string $field_name
   *   The field name for which the data should be retrieved.
   * @param int $delta
   *   Optionally: a delta for a specific item of the field.
   * @param string $property
   *   Optionally: The property name inside each item.
   *
   * @see self::getFieldData()
   */
  public static function getFieldProperty(ContentEntityInterface $entity, $field_name, $delta = NULL, $property = 'value') {
    return self::getFieldData($entity, $field_name, $delta, $property);
  }

  /**
   * Load an original ID for an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The source entity.
   */
  public static function getOriginalIdFromEntity(ContentEntityInterface $entity) {
    return self::getFieldData($entity, 'field_original_id', 0, 'value');

  }

}
