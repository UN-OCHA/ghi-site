<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Base class for API base objects.
 */
interface BaseObjectInterface extends ApiObjectInterface {

  /**
   * Get the name of the API object.
   *
   * @return string
   *   A name for the object.
   */
  public function getName();

  /**
   * Get the short name of the API object if it's available.
   *
   * @return string
   *   A short name for the object, or the original name if the short name is
   *   not available.
   */
  public function getShortName();

}
