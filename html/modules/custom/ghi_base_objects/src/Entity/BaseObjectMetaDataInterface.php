<?php

namespace Drupal\ghi_base_objects\Entity;

/**
 * Provides an interface for defining Base object entities.
 *
 * @ingroup ghi_base_objects
 */
interface BaseObjectMetaDataInterface {

  /**
   * Get meta data to be used in the page title.
   *
   * @return array
   *   An array of items that can be printed after the page title.
   */
  public function getPageTitleMetaData();

}
