<?php

namespace Drupal\hpc_common\Traits;

/**
 * Trait to help with entities.
 */
trait EntityHelperTrait {

  /**
   * Loads entities based on an ID in the format entity_type:entity_id.
   *
   * @param array|string $ids
   *   An array of IDs.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   An array of loaded entities, keyed by an ID.
   */
  public static function loadEntitiesByCompositeIds($ids) {
    if (!is_array($ids)) {
      $ids = explode(' ', $ids);
    }
    $ids = array_filter($ids);

    $storage = [];
    $entities = [];
    foreach ($ids as $id) {
      [$entity_type_id, $entity_id] = explode(':', $id);
      if (!isset($storage[$entity_type_id])) {
        $storage[$entity_type_id] = self::entityTypeManager()->getStorage($entity_type_id);
      }
      $entities[$entity_type_id . ':' . $entity_id] = $storage[$entity_type_id]->load($entity_id);
    }
    return $entities;
  }

  /**
   * Get the entity type manager service.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  private static function entityTypeManager() {
    return \Drupal::entityTypeManager();
  }

}
