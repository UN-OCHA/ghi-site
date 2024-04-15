<?php

namespace Drupal\ghi_base_objects\Entity;

/**
 * Interface for base objects that are children of other base objects.
 */
interface BaseObjectChildInterface extends BaseObjectInterface {

  /**
   * Get the parent base object that this object belongs to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The base object that this object belongs to.
   */
  public function getParentBaseObject();

}
