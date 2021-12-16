<?php

namespace Drupal\ghi_base_objects\Helpers;

use Drupal\hpc_common\Helpers\EntityHelper;

/**
 * Helper class for base objects.
 */
class BaseObjectHelper extends EntityHelper {

  /**
   * Load a base object from it's original ID.
   *
   * @param int $original_id
   *   The original id to look up.
   * @param string $bundle
   *   The bundle that the requested node belongs to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The base object or NULL|FALSE if not found or if found too many.
   */
  public static function getBaseObjectFromOriginalId($original_id, $bundle) {
    $nodes = &drupal_static(__FUNCTION__, []);
    if (empty($nodes[$bundle])) {
      $nodes[$bundle] = [];
    }
    if (empty($nodes[$bundle][$original_id])) {
      $result = self::getBaseObjectsFromOriginalIds([$original_id], $bundle);
      if (is_array($result) && count($result) > 1) {
        return NULL;
      }
      $nodes[$bundle][$original_id] = $result ? reset($result) : NULL;
    }
    return $nodes[$bundle][$original_id];
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
    $nodes = &drupal_static(__FUNCTION__, []);
    if (empty($nodes[$bundle])) {
      $nodes[$bundle] = [];
    }
    $requested_nodes = array_intersect_key($nodes[$bundle], array_flip($original_ids));
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
      foreach ($result as $entity) {
        $nodes[$bundle][self::getFieldData($entity, 'field_original_id', 0, 'value')] = $entity;
      }

    }
    return array_intersect_key($nodes[$bundle], array_flip($original_ids));
  }

}
