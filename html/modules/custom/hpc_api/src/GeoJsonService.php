<?php

namespace Drupal\hpc_api;

/**
 * Class representing an endpoint query.
 *
 * Includes data retrieval and error handling.
 */
class GeoJsonService {

  const GEO_JSON_DIR = 'public://geojson';
  const GEO_JSON_LIFETIME = 24 * 60 * 60;

  public function getGeoJsonLocalFilePath($filepath, $refresh = FALSE) {
    // The geodata exits only on production, so we replace the domain name,
    // whatever it is, with the APIs production domain.
    $filepath_remote = preg_replace('/(https?:\/\/)(.*?)\/(.*)/', '${1}api.hpc.tools/${3}', $filepath);

    // First see if we have a local copy already.
    $local_path = self::GEO_JSON_DIR . '/' . basename($filepath);
    if (file_exists($local_path) && !$refresh) {
      return $local_path;
    }
    // No local copy. Get it from remote.
    $geo_json = @file_get_contents($filepath_remote);
    if (!empty($geo_json)) {
      // Store it locally for faster access in the future.
      file_put_contents($local_path, $geo_json);
    }
    return $local_path;
  }

  /**
   * Get the geo json data from the API.
   *
   * @param string $filepath
   *   The filepath for the geojson file.
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return object
   *   The geo json data object.
   */
  public function getGeoJson($filepath, $refresh = FALSE) {
    $local_path = $this->getGeoJsonLocalFilePath($filepath, $refresh);
    $geo_json = file_get_contents($local_path);
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
