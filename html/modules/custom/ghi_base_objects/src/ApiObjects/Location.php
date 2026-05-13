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
   * The name of the location.
   *
   * @var string|null
   */
  protected ?string $name;

  /**
   * The admin level of the location.
   *
   * @var int
   */
  protected int $adminLevel;

  /**
   * The pcode of the location.
   *
   * @var string|null
   */
  protected ?string $pcode;

  /**
   * The ISO3 code of the location.
   *
   * @var string|null
   */
  protected ?string $iso3;

  /**
   * The country id of the location.
   *
   * @var int
   */
  protected int $countryId;

  /**
   * The ISO3 code of the country.
   *
   * @var string
   */
  protected string $countryIso3;

  /**
   * The lat/lng coordinates.
   *
   * @var array
   */
  protected array $latLng;

  /**
   * The id of the parent location.
   *
   * @var int|null
   */
  protected ?int $parentId;

  /**
   * The timestamp when the location becomes valid.
   *
   * @var string|null
   */
  protected ?string $validOn;

  /**
   * The status of the location.
   *
   * @var string
   */
  protected string $status;

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
    'ParentId',
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
  public function __construct(object $data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->adminLevel = $data->AdminLevel;
    $this->pcode = $data->Pcode ?? NULL;
    $this->iso3 = $data->ISO3 ?? NULL;
    $this->countryId = $data->CountryId;
    $this->countryIso3 = $data->CountryISO3 ?? NULL;
    $this->latLng = [(string) $data->Latitude, (string) $data->Longitude];
    $this->parentId = $data->ParentId ?? NULL;
    $this->validOn = ($data->ActiveUntil ?? NULL) ? substr($data->ActiveUntil, 0, strlen($data->ActiveUntil) - 3) : NULL;
    $this->status = strtolower($data->RecordStatus ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid(): string {
    return md5(implode('_', [
      $this->id(),
      $this->status,
      ($this->validOn ?: 'current'),
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
    return $this->isCountry() ? $this->iso3 : ($this->countryIso3 ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getAdminLevel(): int {
    return $this->adminLevel;
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
   * Get the parent id.
   *
   * @return int|null
   *   The parent id or NULL.
   */
  public function getParentId(): ?int {
    return $this->parentId;
  }

  /**
   * Check if the location represents a country.
   *
   * @return bool
   *   TRUE if it is a country, FALSE otherwise.
   */
  public function isCountry(): bool {
    return $this->getAdminLevel() == 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoJsonVersion(): string {
    $version = 'current';
    if ($this->validOn && $this->status == 'expired') {
      $version = (string) date('Y', $this->validOn);
    }
    return $version;
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoJsonLocationData(): array {
    $array = $this->toArray() + [
      'filepath' => $this->getGeoJsonFileUrl($this),
    ];
    return $array;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags($this->cacheTags, [$this->getUuid()]);
  }

}
