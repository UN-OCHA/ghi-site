<?php

namespace Drupal\ghi_base_objects\Helpers;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\hpc_common\Helpers\EntityHelper;

/**
 * Helper class for base objects.
 */
class BaseObjectHelper extends EntityHelper {

  /**
   * Get the name of the field that stores the base object references.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check.
   *
   * @return string|null
   *   The field name if found.
   */
  public static function getBaseObjectFieldName(FieldableEntityInterface $entity) {
    foreach ($entity->getFieldDefinitions() as $field_definition) {
      if ($field_definition->getType() != 'entity_reference') {
        continue;
      }
      $settings = $field_definition->getSettings();
      if ($settings['target_type'] != 'base_object') {
        continue;
      }
      return $field_definition->getName();
    }
    return NULL;
  }

  /**
   * Get a base object from an entity.
   *
   * This first checks if the entity has field 'field_base_object'. If not, it
   * also checks if the entity has a field 'field_entity_reference' and bubbles
   * up that reference.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check.
   * @param string $bundle
   *   An optional bundle argument.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   A base object if one is found, NULL otherwhise.
   */
  public static function getBaseObjectFromNode(FieldableEntityInterface $entity, $bundle = NULL) {
    if ($bundle !== NULL) {
      $base_objects = self::getBaseObjectsFromNode($entity);
      if (!empty($base_objects)) {
        foreach ($base_objects as $base_object) {
          if ($bundle == $base_object->bundle()) {
            return $base_object;
          }
        }
      }
      return NULL;
    }
    $field_name = self::getBaseObjectFieldName($entity);
    $base_object = $field_name ? $entity->get($field_name)->entity : NULL;
    if (!$base_object) {
      $parent_node = $entity->hasField('field_entity_reference') ? $entity->get('field_entity_reference')->entity : NULL;
      $base_object = $parent_node ? self::getBaseObjectFromNode($parent_node) : NULL;
    }
    return $base_object;
  }

  /**
   * Get all base objects from an entity.
   *
   * This first checks if the entity has field 'field_base_object'. If not, it
   * also checks if the entity has a field 'field_entity_reference' and bubbles
   * up that reference.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity to check.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]|null
   *   All base objects if found, NULL otherwhise.
   */
  public static function getBaseObjectsFromNode(FieldableEntityInterface $entity) {
    $field_name = self::getBaseObjectFieldName($entity);
    $base_objects = $field_name ? $entity->get($field_name)->referencedEntities() : NULL;
    if (!$base_objects) {
      $parent_node = $entity->hasField('field_entity_reference') ? $entity->get('field_entity_reference')->entity : NULL;
      $base_objects = $parent_node ? self::getBaseObjectsFromNode($parent_node) : NULL;
    }
    if (empty($base_objects)) {
      return $base_objects;
    }
    // We support an additional level of object chaining here for plan and
    // governing entities.
    foreach ($base_objects as $base_object) {
      if ($base_object->hasField('field_plan') && $plan_object = $base_object->get('field_plan')->entity) {
        $base_objects[] = $plan_object;
      }
    }
    return $base_objects;
  }

  /**
   * Load a base object from it's original ID.
   *
   * @param int $original_id
   *   The original id to look up.
   * @param string $bundle
   *   The bundle that the requested node belongs to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   The base object or NULL|FALSE if not found or if found too many.
   */
  public static function getBaseObjectFromOriginalId($original_id, $bundle) {
    $result = self::getBaseObjectsFromOriginalIds([$original_id], $bundle);
    return count($result) ? reset($result) : NULL;
  }

  /**
   * Load multple base objects from it's original IDs.
   *
   * @param array $original_ids
   *   The array with original ids to look up.
   * @param string $bundle
   *   The bundle that the requested nodes belong to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   An array of base objects.
   */
  public static function getBaseObjectsFromOriginalIds(array $original_ids, $bundle) {
    if (empty($original_ids) || empty($bundle)) {
      return NULL;
    }
    $objects = &drupal_static(__FUNCTION__, []);
    if (empty($objects[$bundle])) {
      $objects[$bundle] = [];
    }
    $requested_nodes = array_intersect_key($objects[$bundle], array_flip($original_ids));
    if (count($requested_nodes) == count($original_ids)) {
      return $requested_nodes;
    }
    else {
      $result = \Drupal::entityTypeManager()
        ->getStorage('base_object')
        ->loadByProperties([
          'type' => $bundle,
          'field_original_id' => $original_ids,
        ]);
      if (empty($result)) {
        return $result;
      }
      /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $result */
      foreach ($result as $entity) {
        $objects[$entity->bundle()][$entity->getSourceId()] = $entity;
      }

    }
    return array_intersect_key($objects[$bundle], array_flip($original_ids));
  }

}
