<?php

namespace Drupal\hpc_api\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject {

  const GEO_JSON_DIR = 'public://geojson';
  const GEO_JSON_LIFETIME = 24 * 60 * 60;

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
    // The geodata exits only on production, so we replace the domain name,
    // whatever it is, with the APIs production domain.
    $filepath = preg_replace('/(https?:\/\/)(.*?)\/(.*)/', '${1}api.hpc.tools/${3}', $this->filepath);

    // First see if we have a local copy already.
    $local_path = self::GEO_JSON_DIR . '/' . basename($this->filepath);
    if (file_exists($local_path) && !$refresh) {
      if (filemtime($local_path) < REQUEST_TIME - self::GEO_JSON_LIFETIME) {
        $this->getGeoJson($filepath, TRUE);
      }
      else {
        $geo_json = file_get_contents($local_path);
      }
    }
    else {
      // No local copy. Get it from remote.
      $geo_json = @file_get_contents($filepath);
      if (!empty($geo_json)) {
        // Store it locally for faster access in the future.
        file_put_contents($local_path, $geo_json);
      }
    }
    if (empty($geo_json)) {
      return FALSE;
    }
    $geo_data = json_decode($geo_json);
    if (empty($geo_data->features)) {
      return FALSE;
    }
    return $geo_data->features[0];
  }

}
