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
      'id' => $data->id,
      'name' => $data->name ?: 'Admin area ' . $data->externalId,
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
   * {@inheritdoc}
   */
  public function getIso3(): ?string {
    return $this->isCountry() ? $this->iso3 : $this->getParentCountry()?->getIso3();
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
