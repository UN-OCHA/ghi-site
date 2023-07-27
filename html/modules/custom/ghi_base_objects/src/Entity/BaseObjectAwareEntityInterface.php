<?php

namespace Drupal\ghi_base_objects\Entity;

/**
 * Defines an interface for entities that have a connection with a base object.
 */
interface BaseObjectAwareEntityInterface {

  /**
   * Get the base object that is connected to the entity.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The base object connected to the entity.
   */
  public function getBaseObject();

  /**
   * Get the base object type associated with this bundle.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface
   *   The base object type.
   */
  public static function getBaseObjectType();

}
