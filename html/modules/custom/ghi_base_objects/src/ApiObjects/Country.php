<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API country objects.
 */
class Country extends BaseObject {

  const GRAPHQL_ITEMS = "
    Id
    HpcId
    Name
    ISO3
    Pcode
    Latitude
    Longitude
  ";

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'iso3' => $data->ISO3 ?? NULL,
      'latLng' => [(string) $data->Latitude, (string) $data->Longitude],
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
