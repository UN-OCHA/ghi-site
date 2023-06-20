<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'location_id' => $data->id,
      'location_name' => $data->name,
      'admin_level' => $data->adminLevel,
      'pcode' => $data->pcode,
      'latLng' => [(string) $data->latitude, (string) $data->longitude],
      'filepath' => !empty($data->filepath) ? $data->filepath : NULL,
      'parent_id' => $data->parentId,
    ];
  }

  /**
   * Get the geo json data from the API.
   *
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return object
   *   The geo json data object.
   */
  public function getGeoJson($refresh = FALSE) {
    $geojson_service = self::getGeoJsonService();
    return $geojson_service->getGeoJson($this->filepath, $refresh);
  }

  /**
   * Get the geojson service.
   *
   * @return \Drupal\hpc_api\GeoJsonService
   *   The geojson service.
   */
  public static function getGeoJsonService() {
    return \Drupal::service('hpc_api.geojson');
  }

}
