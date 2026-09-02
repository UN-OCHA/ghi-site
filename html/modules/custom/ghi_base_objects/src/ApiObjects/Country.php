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

  /**
   * The pcode of the country.
   *
   * @var string|null
   */
  protected ?string $pcode;

  /**
   * The ISO3 code of the country.
   *
   * @var string|null
   */
  protected ?string $iso3;

  /**
   * The lat/lng coordinates.
   *
   * @var array
   */
  protected array $latLng;

  /**
   * The timestamp when the country becomes valid.
   *
   * @var string|null
   */
  protected ?string $validOn;

  /**
   * The status of the country.
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
    'Pcode',
    'Latitude',
    'Longitude',
    'RecordStatus',
    'ActiveUntil',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->pcode = $data->Pcode ?? NULL;
    $this->iso3 = $data->ISO3 ?? NULL;
    $this->latLng = [(string) ($data->Latitude ?? 0), (string) ($data->Longitude ?? 0)];
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
  public function getIso3(): ?string {
    return $this->iso3;
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
    return $this->pcode;
  }

  /**
   * Get the latlng data for the country.
   *
   * @return array
   *   An array with 2 values, first is Latitude, second is Longitude.
   */
  public function getLatLng(): array {
    return $this->latLng;
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
    return $this->toArray() + [
      'name' => $this->name,
      'pcode' => $this->pcode,
      'iso3' => $this->iso3,
      'latLng' => $this->latLng,
      'valid_on' => $this->validOn,
      'status' => $this->status,
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
