<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Base class for API base objects.
 */
interface BaseObjectInterface {

  /**
   * Get the ID of the API object.
   *
   * @return int
   *   The id of the object.
   */
  public function getId();

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
