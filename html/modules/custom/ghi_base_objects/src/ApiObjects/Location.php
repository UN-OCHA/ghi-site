<?php

namespace Drupal\ghi_base_objects\ApiObjects;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject {

  /**
   * Define the paths to the fallback files for geojson country data.
   *
   * The paths are relative to the module directory.
   *
   * The UN dataset that is the base for the mapbox style.
   */
  const GEOJSON_FALLBACK_FILE_UN = 'assets/geojson/wrl_all_country_1m.geojson';

  /**
   * An alternative source.
   *
   * This comes from
   * https://github.com/datasets/geo-countries via
   * https://datahub.io/core/geo-countries and
   * https://www.naturalearthdata.com/downloads/10m-cultural-vectors/.
   */
  const GEOJSON_FALLBACK_FILE_OTHER = 'assets/geojson/countries.geojson';

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
   * @return string
   *   A local path.
   */
  public function getGeoJsonLocalFilePath($refresh = FALSE) {
    $geojson_service = self::getGeoJsonService();
    $file_url_generator = self::fileUrlGenerator();
    if ($this->filepath && $uri = $geojson_service->getGeoJsonLocalFilePath($this->filepath, $refresh)) {
      // If we have a filepath, let's point to it. This comes from the API and
      // we store local copies of it.
      return $uri ? $file_url_generator->generate($uri)->toString() : NULL;
    }
    if (!$this->iso3) {
      return NULL;
    }
    // Otherwise let's see if we can get another type of local file that is
    // extracted from a static geojson source and fetched via
    // self::getGeoJsonFallback().
    $local_filename = $this->iso3 . '.json';
    if (!$geojson_service->localFileExists($local_filename)) {
      $this->getGeoJsonFallback();
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
    $geojson = $this->filepath ? $geojson_service->getGeoJson($this->filepath, $refresh) : FALSE;
    if (!$geojson) {
      $geojson = $this->getGeoJsonFallback();
    }
    return $geojson;
  }

  /**
   * Use a fallback to retrieve geojson polygon data for a location.
   *
   * @return object|false
   *   The geo json data object or FALSE.
   */
  private function getGeoJsonFallback() {
    if ($this->admin_level > 0) {
      // The fallback is available only for admin level 0 locations.
      return FALSE;
    }
    $geojson_service = self::getGeoJsonService();
    $local_filename = $this->iso3 . '.json';
    if ($geojson_service->localFileExists($local_filename)) {
      return $geojson_service->getLocalFileContent($local_filename);
    }

    $features = NULL;
    $geojson_feature_file = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/assets/geojson/countries/' . $local_filename;
    if (file_exists($geojson_feature_file)) {
      $lines = array_filter(explode("\n", file_get_contents($geojson_feature_file)));
      $features = !empty($lines) ? array_map(function ($line) {
        return json_decode($line);
      }, $lines) : NULL;
    }
    if ($features === NULL) {
      $geojson_file = self::moduleHandler()->getModule('ghi_base_objects')->getPath() . '/' . self::GEOJSON_FALLBACK_FILE_UN;
      if (!file_exists($geojson_file)) {
        return FALSE;
      }

      // Extract the features for the current location based on the iso3 code.
      $content = json_decode(file_get_contents($geojson_file));

      $features = $content ? array_filter($content->features, function ($item) {
        return property_exists($item->properties, 'iso3cd') && $item->properties->iso3cd == $this->iso3
            || property_exists($item->properties, 'ISO_A3') && $item->properties->ISO_A3 == $this->iso3
            || property_exists($item->properties, 'ISO3_CODE') && $item->properties->ISO3_CODE == $this->iso3;
      }) : [];
      if (empty($features)) {
        return FALSE;
      }
      $features = array_values(array_map(function ($feature) {
        unset($feature->properties);
        return $feature;
      }, $features));
    }

    $geojson = (object) [
      'type' => 'Feature',
      'geometry' => (object) [
        'type' => 'GeometryCollection',
        'geometries' => array_map(function ($feature) {
          return $feature->geometry;
        }, $features),
      ],
      'properties' => (object) [
        'location_id' => $this->id(),
      ],
    ];
    $geojson_service->writeGeoJsonFile($local_filename, json_encode($geojson));
    return $geojson;
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
