<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject {

  const GEO_JSON_DIR = 'public://geojson';

  /**
   * The parent country.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Location
   */
  private $parentCountry = NULL;

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
      'parent_id' => $data->parentId,
      'valid_on' => $data->validOn,
      'children' => $data->children ?? [],
    ];
  }

  /**
   * Set the parent country for a location.
   *
   * @param \Drupal\ghi_base_objects\ApiObjects\Location $country
   *   The parent country.
   */
  public function setParentCountry(Location $country) {
    $this->parentCountry = $country;
  }

  /**
   * Get the parent country for a location.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location
   *   The parent country.
   */
  public function getParentCountry() {
    return $this->parentCountry;
  }

  /**
   * Get the iso3 code.
   *
   * @return string
   *   The iso3 code.
   */
  public function getIso3() {
    return $this->isCountry() ? $this->iso3 : $this->getParentCountry()->getIso3();
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
   * Get the pcode.
   *
   * @return string
   *   The pcode.
   */
  public function getPcode() {
    return $this->pcode;
  }

  /**
   * Get the lat/lng coordinates.
   *
   * @return array
   *   Array with 2 items: [latitude, longitude].
   */
  public function getLatLng() {
    return [
      $this->getLatitude(),
      $this->getLongitude(),
    ];
  }

  /**
   * Get the latitude.
   *
   * @return string
   *   The latitude.
   */
  public function getLatitude() {
    return $this->latLng[0];
  }

  /**
   * Get the longitude.
   *
   * @return string
   *   The longitude.
   */
  public function getLongitude() {
    return $this->latLng[1];
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
   * Check if we have a geojson file for this location.
   *
   * @return bool
   *   TRUE if a geojson file is there, FALSE otherwise.
   */
  public function hasGeoJsonFile() {
    return $this->getGeoJsonSourceFilePath() !== NULL;
  }

  /**
   * Get the path to the geojson shape file for the location.
   *
   * @param string $version
   *   The version to retrieve.
   * @param bool $minified
   *   Whether a minified file should be retrieved.
   *
   * @return string|null
   *   The path to the locally stored file inside our module directory. Or NULL
   *   if the file can't be found.
   */
  public function getGeoJsonSourceFilePath($version = 'current', $minified = TRUE) {
    $directory = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson';

    // The source file for countries comes from a local asset.
    $filepath = $this->buildGeoJsonSourceFilePath($version, $minified);
    if (!$filepath) {
      return NULL;
    }

    $filepath_asset = $directory . '/' . $filepath;
    if (!file_exists($filepath_asset)) {
      // If the file is not found, try the non-minified version once.
      return $minified ? $this->getGeoJsonSourceFilePath($version, FALSE) : NULL;
    }
    return $filepath_asset;
  }

  /**
   * Build the path to the geojson source files inside this modules directory.
   *
   * @param string $version
   *   The version to retrieve.
   * @param bool $minified
   *   Whether a minified file should be retrieved.
   *
   * @return string
   *   A path relative to the this modules geojson asset file directory in
   *   [MODULE_PATH]/assets/geojson.
   */
  private function buildGeoJsonSourceFilePath($version = 'current', $minified = TRUE) {
    if (!$this->getIso3()) {
      return NULL;
    }
    $path_parts = [
      $this->getIso3(),
      $version,
    ];
    if ($this->isCountry()) {
      // Country shape files are directly in the root level.
      $path_parts[] = $this->getIso3() . '_0' . ($minified ? '.min' : '') . '.geojson';
    }
    elseif (!empty($this->getAdminLevel()) && !empty($this->getPcode())) {
      // Admin 1+ shape files are inside a level specific subdirectory.
      $path_parts[] = 'adm' . $this->getAdminLevel();
      // And they are simply named like their pcode.
      $path_parts[] = $this->getPcode() . '.geojson';
    }
    else {
      return NULL;
    }
    return implode('/', $path_parts);
  }

  /**
   * Get the path to the geojson file inside the public file directory.
   *
   * This checks if a geojson file for this location is present in the source,
   * and if so, it makes sure to copy it over to the public file system.
   *
   * @return string
   *   The path to the geojson file inside the public file directory.
   */
  public function getGeoJsonPublicFilePath() {
    $filepath = $this->getGeoJsonSourceFilePath();
    if (!$filepath) {
      return NULL;
    }
    $public_filepath = self::GEO_JSON_DIR . '/' . md5($this->id() . '_' . $this->valid_on) . '.geojson';
    if (!file_exists($public_filepath)) {
      copy($filepath, $public_filepath);
    }
    return $public_filepath;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    $array = parent::toArray();
    $geojson_public_path = $this->getGeoJsonPublicFilePath();
    $array['filepath'] = $geojson_public_path ? $this->fileUrlGenerator()->generate($geojson_public_path)->toString() : NULL;
    return $array;
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
