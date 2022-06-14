<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Base object type entities.
 */
interface BaseObjectTypeInterface extends ConfigEntityInterface {

  /**
   * Determines whether the base object has a year.
   *
   * Usually an object in the HPC API does not have a year associated to it.
   * A notable exception is an HPC plan object, which is tied to one or
   * multiple years in the API.
   *
   * @return bool
   *   TRUE if the object has a year, or FALSE otherwhise.
   */
  public function hasYear();

  /**
   * Determines whether the base object needs a year to fetch meaningful data.
   *
   * @return bool
   *   TRUE if the object needs a year, or FALSE otherwhise.
   */
  public function needsYearForDataRetrieval();

}
