<?php

namespace Drupal\ghi_base_objects\ApiObjects;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\ghi_geojson\Traits\GeoJsonLocationTrait;

/**
 * Abstraction class for API location objects.
 */
class Location extends BaseObject implements GeoJsonLocationInterface {

  use GeoJsonLocationTrait;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'ISO3',
    'CountryId',
    'CountryISO3',
    'Pcode',
    'AdminLevel',
    'Latitude',
    'Longitude',
    'RecordStatus',
    'ActiveUntil',
  ];

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
      'id' => $data->Id,
      'name' => $data->Name,
      'admin_level' => $data->AdminLevel,
      'pcode' => $data->Pcode ?? NULL,
      'iso3' => $data->ISO3 ?? NULL,
      'country_id' => $data->CountryId,
      'country_iso3' => $data->CountryISO3 ?? NULL,
      'latLng' => [(string) $data->Latitude, (string) $data->Longitude],
      'valid_on' => ($data->ActiveUntil ?? NULL) ? substr($data->ActiveUntil, 0, strlen($data->ActiveUntil) - 3) : NULL,
      'status' => strtolower($data->RecordStatus ?? ''),
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
  public function getName(): ?string {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getIso3(): ?string {
    return $this->isCountry() ? $this->iso3 : ($this->country_iso3 ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLevel(): int {
    return $this->admin_level;
  }

  /**
   * {@inheritdoc}
   */
  public function getPcode(): ?string {
    return $this->pcode;
  }

  /**
   * Get the lat/lng coordinates.
   *
   * @return array
   *   Array with 2 items: [latitude, longitude].
   */
  public function getLatLng(): array {
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
  public function getCacheTags() {
    return Cache::mergeTags($this->cacheTags, [$this->getUuid()]);
  }

}
