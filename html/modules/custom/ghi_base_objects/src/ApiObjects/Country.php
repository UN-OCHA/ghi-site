<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\ghi_geojson\GeoJson;
use Drupal\ghi_geojson\GeoJsonLocationInterface;

/**
 * Abstraction class for API country objects.
 */
class Country extends BaseObject implements GeoJsonLocationInterface {

  const GRAPHQL_ITEMS = "
    Id
    Name
    ISO3
    Pcode
    Latitude
    Longitude
    RecordStatus
    ActiveUntil
  ";

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'pcode' => $data->Pcode ?? NULL,
      'iso3' => $data->ISO3 ?? NULL,
      'latLng' => [(string) $data->Latitude, (string) $data->Longitude],
      'valid_on' => ($data->ActiveUntil ?? NULL) ? substr($data->ActiveUntil, 0, strlen($data->ActiveUntil) - 3) : NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid() {
    return md5(implode('_', [
      $this->id(),
      $this->status,
      ($this->valid_on ?: 'current'),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getIso3(): string {
    return $this->map->iso3;
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLevel(): int {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getPcode(): string {
    return $this->map->pcode;
  }

  /**
   * Get the latlng data for the country.
   *
   * @return array
   *   An array with 2 values, first is Latitude, second is Longitude.
   */
  public function getLatLng(): array {
    return $this->map->latLng;
  }

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
   * {@inheritdoc}
   */
  public function getGeoJsonVersion() {
    $version = 'current';
    if ($this->valid_on && $this->status == 'expired') {
      $version = date('Y', $this->valid_on);
    }
    return $version;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    $array = parent::toArray();
    $geojson_public_path = $this->geojson()->getGeoJsonPublicFilePath($this);
    $array['filepath'] = $geojson_public_path ? $this->fileUrlGenerator()->generate($geojson_public_path)->toString() : NULL;
    return $array;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags($this->cacheTags, [$this->getUuid()]);
  }

  /**
   * Get the geojson service.
   *
   * @return \Drupal\ghi_geojson\GeoJson
   *   The geojson service.
   */
  public static function geojson(): GeoJson {
    return \Drupal::service('geojson');
  }

  /**
   * Get the file url generator service.
   *
   * @return \Drupal\Core\File\FileUrlGeneratorInterface
   *   The file url generator service.
   */
  public static function fileUrlGenerator(): FileUrlGeneratorInterface {
    return \Drupal::service('file_url_generator');
  }

}
