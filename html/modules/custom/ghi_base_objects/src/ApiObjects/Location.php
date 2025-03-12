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
      'location_name' => $data->name ?: 'Admin area ' . $data->externalId,
      'admin_level' => $data->adminLevel,
      'pcode' => $data->pcode,
      'iso3' => $data->iso3,
      'latLng' => [(string) $data->latitude, (string) $data->longitude],
      'filepath' => !empty($data->filepath) ? $data->filepath : NULL,
      'parent_id' => $data->parentId,
      'children' => $data->children ?? [],
    ];
  }

  /**
   * Get the path to the local copy of the geo json data.
   *
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return string|null
   *   A local path.
   */
  public function getGeoJsonLocalFilePath($refresh = FALSE) {
    $geojson_service = self::getGeoJsonService();
    $file_url_generator = self::fileUrlGenerator();
    if ($this->admin_level > 0 && $this->filepath && $uri = $geojson_service->getGeoJsonLocalFilePath($this->filepath, $refresh)) {
      // For admin level 1+, if we have a filepath, let's point to it. This
      // comes from the API and we store local copies of it.
      return $uri ? $file_url_generator->generate($uri)->toString() : NULL;
    }
    // Otherwise let's see if we can get another type of local file that is
    // extracted from a static geojson source and fetched via
    // self::getSelfHostedGeoJson().
    $local_filename = $this->getGeoJsonFilename();
    if (!$local_filename) {
      return NULL;
    }
    if (!$geojson_service->localFileExists($local_filename)) {
      $this->getSelfHostedGeoJson();
    }
    $filepath = $geojson_service->getLocalFilePath($local_filename);
    return $filepath ? $file_url_generator->generate($filepath)->toString() : NULL;
  }

  /**
   * Get the geo json data from the API.
   *
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return object|false
   *   The geo json data object or FALSE.
   */
  public function getGeoJson($refresh = FALSE) {
    $geojson_service = self::getGeoJsonService();
    if ($this->admin_level == 0) {
      return $this->getSelfHostedGeoJson();
    }
    return $this->filepath ? $geojson_service->getGeoJson($this->filepath, $refresh) : FALSE;
  }

  /**
   * Use a fallback to retrieve geojson polygon data for a location.
   *
   * @return object|false
   *   The geo json data object or FALSE.
   */
  private function getSelfHostedGeoJson() {
    if ($this->admin_level > 0) {
      // The fallback is available only for admin level 0 locations.
      return FALSE;
    }
    $geojson_service = self::getGeoJsonService();
    $local_filename = $this->getGeoJsonFilename();
    if ($geojson_service->localFileExists($local_filename)) {
      return $geojson_service->getLocalFileContent($local_filename);
    }

    $geojson_feature_file = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson/' . $this->iso3 . '/current/' . $local_filename;
    if (!file_exists($geojson_feature_file)) {
      return FALSE;
    }

    $geojson = json_decode(file_get_contents($geojson_feature_file));
    if (empty($geojson)) {
      return FALSE;
    }

    $geojson_service->writeGeoJsonFile($local_filename, json_encode($geojson));
    return $geojson;
  }

  /**
   * Get the file name for a self hosted geojson file.
   *
   * @return string|null
   *   The pattern is [ISO3]_[ADMIN_LEVEL].geojson for admin 0,
   *   [ISO3]_[ADMIN_LEVEL]_[PCODE].geojson for admin 1+.
   */
  private function getGeoJsonFilename() {
    if (empty($this->iso3)) {
      return NULL;
    }
    if ($this->admin_level == 0) {
      return $this->iso3 . '_0.geojson';
    }
    if (empty($this->admin_level) || empty($this->pcode)) {
      return NULL;
    }
    return $this->iso3 . '_' . $this->admin_level . '_' . $this->pcode . '.geojson';
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

  /**
   * Get the file url generator service.
   *
   * @return \Drupal\Core\File\FileUrlGeneratorInterface
   *   The file url generator service.
   */
  public static function fileUrlGenerator() {
    return \Drupal::service('file_url_generator');
  }

  /**
   * Get the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler service.
   */
  public static function moduleHandler() {
    return \Drupal::service('module_handler');
  }

}
