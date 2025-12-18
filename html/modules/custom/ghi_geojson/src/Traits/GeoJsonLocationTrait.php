<?php

namespace Drupal\ghi_geojson\Traits;

use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_geojson\GeoJsonLocationInterface;

trait GeoJsonLocationTrait {

  /**
   * Check if we have a geojson file for this location.
   *
   * @return bool
   *   TRUE if a geojson file is there, FALSE otherwise.
   */
  public function hasGeoJsonFile(): bool {
    return $this->geojson()->getGeoJsonSourceFilePath($this) !== NULL;
  }

  /**
   * Get the file url to the geojson data for the given location.
   *
   * @param \Drupal\ghi_geojson\GeoJsonLocationInterface $location
   *   The location for which to get the file url.
   *
   * @return string|null
   *   A file url as a string or NULL.
   */
  public function getGeoJsonFileUrl(GeoJsonLocationInterface $location): ?string {
    $geojson_public_path = self::geojson()->getGeoJsonPublicFilePath($location);
    return $geojson_public_path ? self::fileUrlGenerator()->generate($geojson_public_path)->toString() : NULL;
  }

  /**
   * Get the geojson service.
   *
   * @return \Drupal\ghi_geojson\GeoJson
   *   The geojson service.
   */
  private static function geojson(): GeoJson {
    return \Drupal::service('geojson');
  }

  /**
   * Get the file url generator service.
   *
   * @return \Drupal\Core\File\FileUrlGeneratorInterface
   *   The file url generator service.
   */
  private static function fileUrlGenerator(): FileUrlGeneratorInterface {
    return \Drupal::service('file_url_generator');
  }

}