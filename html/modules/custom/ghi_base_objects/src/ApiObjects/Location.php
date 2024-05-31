<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject {

  /**
   * {@inheritdoc}
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
   * Get the path to the local copy of the geo json data.
   *
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return string
   *   A local path.
   */
  public function getGeoJsonLocalFilePath($refresh = FALSE) {
    $geojson_service = self::getGeoJsonService();
    $uri = $geojson_service->getGeoJsonLocalFilePath($this->filepath, $refresh);
    return $uri ? \Drupal::service('file_url_generator')->generate($uri)->toString() : NULL;
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
   * {@inheritdoc}
   */
  public function toArray() {
    $array = parent::toArray();
    $array['filepath'] = $this->getGeoJsonLocalFilePath();
    return $array;
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
