<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\ghi_geojson\Traits\GeoJsonLocationTrait;

/**
 * Bundle class for plan base objects.
 */
class Country extends BaseObject implements GeoJsonLocationInterface {

  use GeoJsonLocationTrait;

  const BUNDLE = 'country';

  /**
   * {@inheritdoc}
   */
  public function getIso3(): ?string {
    return $this->get('field_country_code')->value;
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
    return NULL;
  }

  /**
   * Get the lat long values for the country.
   *
   * @return string[]
   *   First item is the latitude, second is the longitude.
   */
  public function getLatLong(): array {
    return [
      $this->get('field_latitude')->value,
      $this->get('field_longitude')->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getUuid(): string {
    return md5(implode('_', [
      $this->id(),
      'active',
      'current',
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoJsonVersion(): string {
    return 'current';
  }

  /**
   * {@inheritdoc}
   */
  public function getGeoJsonLocationData(): array {
    return [
      'id' => $this->getSourceId(),
      'name' => $this->getName(),
      'pcode' => $this->getPcode(),
      'iso3' => $this->getIso3(),
      'latLng' => $this->getLatLong(),
      'filepath' => $this->getGeoJsonFileUrl($this),
    ];
  }

}
