<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\ghi_geojson\Traits\GeoJsonLocationTrait;

/**
 * Abstraction class for API country objects.
 */
class Country extends BaseObject implements GeoJsonLocationInterface {

  use GeoJsonLocationTrait;

  const GRAPHQL_DIMENSION_ITEMS = "
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
      'latLng' => [(string) ($data->Latitude ?? 0), (string) ($data->Longitude ?? 0)],
      'valid_on' => ($data->ActiveUntil ?? NULL) ? substr($data->ActiveUntil, 0, strlen($data->ActiveUntil) - 3) : NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid(): string {
    return md5(implode('_', [
      $this->id(),
      $this->status,
      ($this->valid_on ?: 'current'),
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getIso3(): ?string {
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
  public function getPcode(): ?string {
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
   * {@inheritdoc}
   */
  public function getGeoJsonVersion(): string {
    $version = 'current';
    if ($this->valid_on && $this->status == 'expired') {
      $version = (string) date('Y', $this->valid_on);
    }
    return $version;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoJsonLocationData(): array {
    return $this->toArray() + [
      'filepath' => $this->getGeoJsonFileUrl($this),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags($this->cacheTags, [$this->getUuid()]);
  }

}
