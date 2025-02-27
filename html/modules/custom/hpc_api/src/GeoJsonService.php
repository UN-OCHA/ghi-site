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

  /**
   * Get the filepath for the locally stored geojson file.
   *
   * @param string $filepath
   *   A filepath.
   * @param bool $refresh
   *   Whether to force refresh of the data or not.
   *
   * @return string|null
   *   The filepath to the local file or NULL.
   */
  public function getGeoJsonLocalFilePath($filepath, $refresh = FALSE) {
    if (empty($filepath)) {
      return FALSE;
    }
    // The geodata exits only on production, so we replace the domain name,
    // whatever it is, with the APIs production domain.
    $filepath_remote = preg_replace('/(https?:\/\/)(.*?)\/(.*)/', '${1}api.hpc.tools/${3}', $filepath);
    $filename = basename($filepath);

    // First see if we have a local copy already.
    if ($this->localFileExists($filename) && !$refresh) {
      return $this->getLocalFilePath($filename);
    }

    // No local copy. Get it from remote.
    $geo_json = @file_get_contents($filepath_remote);
    if (!empty($geo_json)) {
      // Store it locally for faster access in the future.
      return $this->writeGeoJsonFile($filename, $geo_json);
    }
    return NULL;
  }

  /**
   * Get the geo json data from the API.
   *
   * @param string $filepath
   *   The filepath for the geojson file.
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return object|false
   *   The geo json data object or FALSE.
   */
  public function getGeoJson($filepath, $refresh = FALSE) {
    if (empty($filepath)) {
      return FALSE;
    }
    $local_path = $this->getGeoJsonLocalFilePath($filepath, $refresh);
    if (!$local_path) {
      return FALSE;
    }
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

  /**
   * get the filepath to a local file.
   *
   * @param string $filename
   *   The filename.
   *
   * @return string
   *   The relative path to the filename.
   */
  public function getLocalFilePath($filename) {
    return self::GEO_JSON_DIR . '/' . $filename;
  }

  /**
   * Check if a local file for the given name exists.
   *
   * @param string $filename
   *   The filename to look up.
   *
   * @return bool
   *   TRUE if the file specified by filename exists, FALSE otherwise.
   */
  public function localFileExists($filename) {
    return file_exists($this->getLocalFilePath($filename));
  }

  /**
   * Get the content of the local file.
   *
   * @param string $filename
   *   The filename to fetch the data for.
   *
   * @return string
   *   The content of the local file.
   */
  public function getLocalFileContent($filename) {
    return file_get_contents($this->getLocalFilePath($filename));
  }

  /**
   * Write geojson to a file inside our local directory.
   *
   * @param string $filename
   *   The name for the file.
   * @param string $content
   *   The GeoJson content.
   *
   * @return string|null
   *   The full path to the created file or NULL.
   */
  public function writeGeoJsonFile($filename, $content) {
    if (empty($content)) {
      return NULL;
    }
    $local_path = $this->getLocalFilePath($filename);
    file_put_contents($local_path, $content);
    return $local_path;
  }



}
