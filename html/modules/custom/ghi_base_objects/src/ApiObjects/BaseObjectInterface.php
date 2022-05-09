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
   * @param bool $shortname
   *   Whether to get the shortname if available.
   *
   * @return string
   *   A name for the object.
   */
  public function getName($shortname = FALSE);

}
