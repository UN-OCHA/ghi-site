<?php

namespace Drupal\hpc_api\Query;

/**
 * Base class for import query plugins.
 */
interface ImportQueryInterface {

  /**
   * Get the source data for the import.
   *
   * @return object[]
   *   An array of objects, each representing a row of the import data.
   */
  public function getSourceData();

}
