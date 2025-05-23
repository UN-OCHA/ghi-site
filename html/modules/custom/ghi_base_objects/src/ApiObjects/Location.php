<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\Core\Cache\Cache;

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
      'status' => $data->status,
      'valid_on' => $data->validOn ? substr($data->validOn, 0, strlen($data->validOn) - 3) : NULL,
    ];
  }

  /**
   * Get a UUID for this location.
   *
   * @return string
   *   A string representing a UUID.
   */
  public function getUuid() {
    return md5(implode('_', [
      $this->id(),
      $this->status,
      ($this->valid_on ?: 'current'),
    ]));
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
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   The parent country.
   */
  public function getParentCountry() {
    return $this->parentCountry ?? $this->fetchParentCountry();
  }

  /**
   * Get the iso3 code.
   *
   * @return string|null
   *   The iso3 code or NULL if not found.
   */
  public function getIso3() {
    return $this->isCountry() ? $this->iso3 : $this->getParentCountry()?->getIso3();
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
   * Get the location children.
   *
   * @return array
   *   An array of raw location objects.
   */
  public function getChildren() {
    $data = $this->getRawData();
    return $data->children ?? [];
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
   * Get the version to use for the geojson shapefiles.
   *
   * @return int|string
   *   Returns the year component of the valid_on date for expired locations,
   *   or the string 'current'.
   */
  private function getGeoJsonVersion() {
    $version = 'current';
    if ($this->valid_on && $this->status == 'expired') {
      $version = date('Y', $this->valid_on);
    }
    return $version;
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
  public function getGeoJsonSourceFilePath($version = NULL, $minified = TRUE) {
    if (!$this->getIso3()) {
      return NULL;
    }
    $version = $version ?? $this->getGeoJsonVersion();
    $directory = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson/' . $this->getIso3();
    if ($version != 'current') {
      $directories = glob($directory . '/[0-9][0-9][0-9][0-9]', GLOB_ONLYDIR);
      $directory_years = array_map(function ($dirname) {
        return basename($dirname);
      }, $directories);
      $versions = array_filter($directory_years, function ($directory_year) use ($version) {
        return (int) $directory_year >= (int) $version;
      });
      rsort($versions, SORT_NUMERIC);
      $version = reset($versions) ?: 'current';
    }

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
  private function buildGeoJsonSourceFilePath($version = NULL, $minified = TRUE) {
    if (!$this->getIso3()) {
      return NULL;
    }
    $version = $version ?? $this->getGeoJsonVersion();
    $path_parts = [
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
      $path_parts[] = $this->getPcode() . ($minified ? '.min' : '') . '.geojson';
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
    $public_filepath = self::GEO_JSON_DIR . '/' . $this->getUuid() . '.geojson';
    if (!file_exists($public_filepath) && $filepath = $this->getGeoJsonSourceFilePath()) {
      copy($filepath, $public_filepath);
    }
    return file_exists($public_filepath) ? $public_filepath : NULL;
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
   * Fetch the parent country recursively.
   *
   * @param int $parent_id
   *   A location id.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location object or NULL.
   */
  private function fetchParentCountry($parent_id = NULL) {
    $parent_location = NULL;
    $parent_id = $parent_id ?? $this->parent_id;
    while (!empty($parent_id)) {
      $parent_location = $this->locationsQuery()->getLocation($parent_id);
      $parent_id = $parent_location?->parent_id;
    }
    $this->parentCountry = $parent_location;
    return $parent_location?->getAdminLevel() == 0 ? $parent_location : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags($this->cacheTags, [$this->getUuid()]);
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

  /**
   * Get the locations query.
   *
   * @return \Drupal\ghi_base_objects\Plugin\EndpointQuery\LocationsQuery
   *   The locations query.
   */
  public static function locationsQuery() {
    /** @var \Drupal\hpc_api\Query\EndpointQueryManager $endpoint_query_manager */
    $endpoint_query_manager = \Drupal::service('plugin.manager.endpoint_query_manager');
    return $endpoint_query_manager->createInstance('locations_query');
  }

}
