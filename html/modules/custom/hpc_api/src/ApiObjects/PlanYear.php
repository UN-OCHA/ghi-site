<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Class for plan year objects.
 */
class PlanYear extends ApiObjectBase {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'year' => $data->CalendarYear,
    ];
  }

  /**
   * Get the name of the type.
   *
   * @return string
   *   The name.
   */
  public function getYear() {
    return $this->map->year;
  }

}
