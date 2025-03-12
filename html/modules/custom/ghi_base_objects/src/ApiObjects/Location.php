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
   * Get the admin level.
   *
   * @return int
   *   The admin level.
   */
  public function getAdminLevel() {
    return $this->admin_level;
  }

  /**
   * Check if the location represents a country.
   *
   * @return bool
   *   TRUE if it is a country, FALSE otherwise.
   */
  public function isCountry() {
    return $this->getAdminLevel() == 0;
  }

  /**
   * Get the path to the geojson shape file for the location.
   *
   * @return string|null
   *   The path to the locally stored file inside the geojson directory in the
   *   public file system. Or NULL if the file can't be found.
   */
  private function getGeoJsonFilePath() {
    $geojson_service = self::getGeoJsonService();
    if ($this->isCountry()) {
      // The source file for countries comes from a local asset.
      $filename = $this->getLocalAssetGeoJsonFilename();
      if (!$filename) {
        return NULL;
      }
      $directory = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson/' . $this->iso3 . '/current';
      $filepath_asset = $directory . '/' . $filename;
      if (!file_exists($filepath_asset)) {
        return NULL;
      }
      // We still want the file to served from the geojson directory in the
      // public file system.
      if (!$geojson_service->localFileExists($filename)) {
        // Copy to the geojson directory in the public file system.
        $geojson_service->writeGeoJsonFile($filename, file_get_contents($filepath_asset));
      }
      return $geojson_service->getLocalFilePath($filename);
    }
    else {
      return $geojson_service->getGeoJsonLocalFilePath($this->filepath);
    }
  }

  /**
   * Get the URL to the geosjon shape file.
   *
   * @return string
   *   The URL to the locally stored file inside the geojson directory in the
   *   public file system. Or NULL if the no such file exists.
   */
  private function getGeoJsonFileUrl() {
    $filepath = $this->getGeoJsonFilePath();
    return $filepath ? self::fileUrlGenerator()->generate($filepath)->toString() : NULL;
  }

  /**
   * Get the geo json data for the location if available.
   *
   * @param bool $refresh
   *   Whether to refresh stored data.
   *
   * @return object|false
   *   The geo json data object or FALSE.
   */
  public function getGeoJson($refresh = FALSE) {
    $filepath = $this->getGeoJsonFilePath();
    return $filepath ? json_decode(file_get_contents($filepath)) : NULL;
  }

  /**
   * Get the file name for a self hosted geojson file.
   *
   * @return string|null
   *   The pattern is [ISO3]_[ADMIN_LEVEL].geojson for admin 0,
   *   [ISO3]_[ADMIN_LEVEL]_[PCODE].geojson for admin 1+.
   */
  private function getLocalAssetGeoJsonFilename($minified = TRUE) {
    if (empty($this->iso3)) {
      return NULL;
    }
    if ($this->admin_level == 0) {
      return $this->iso3 . '_0' . ($minified ? '.min' : '') . '.geojson';
    }
    if (empty($this->admin_level) || empty($this->pcode)) {
      return NULL;
    }
    return $this->iso3 . '_' . $this->admin_level . '_' . $this->pcode . ($minified ? '.min' : '') . '.geojson';
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $array = parent::toArray();
    $array['filepath'] = $this->getGeoJsonFileUrl();
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
