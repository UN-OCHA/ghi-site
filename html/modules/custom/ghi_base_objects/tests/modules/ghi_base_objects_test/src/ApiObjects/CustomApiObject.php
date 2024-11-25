<?php

namespace Drupal\ghi_base_objects_test\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API country objects.
 */
class CustomApiObject extends BaseObject {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->id,
      'name' => $data->name,
    ];
  }

}
