<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Base class for API type objects.
 */
abstract class BaseType extends ApiObjectBase {

  /**
   * Map the raw data.
   *
   * This uses only what we needed up to now. More properties can be mapped if
   * needed.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
    ];
  }

  /**
   * Get the name of the type.
   *
   * @return string
   *   The name.
   */
  public function getName() {
    return $this->name;
  }

}
