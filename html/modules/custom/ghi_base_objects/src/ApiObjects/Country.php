<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API country objects.
 */
class Country extends BaseObject {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->id,
      'name' => $data->name,
      'latLng' => [(string) $data->latitude, (string) $data->longitude],
    ];
  }

  /**
   * Get the latlng data for the country.
   *
   * @return array
   *   An array with 2 values, first is Latitude, second is Longitude.
   */
  public function getLatLng() {
    return $this->latLng;
  }

}
