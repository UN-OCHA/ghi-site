<?php

namespace Drupal\ghi_geojson;

/**
 * Interface class for location objects with geosjon data.
 */
interface GeoJsonLocationInterface {

  const MAINTAIN_ARRAY_KEYS = ['latLng'];

  /**
   * Get the iso3 code.
   *
   * @return string|null
   *   The iso3 code or NULL if not found.
   */
  public function getIso3(): ?string;

  /**
   * Get the admin level.
   *
   * @return int
   *   The admin level.
   */
  public function getAdminLevel(): int;

  /**
   * Get the pcode.
   *
   * @return string|null
   *   The pcode.
   */
  public function getPcode(): ?string;

  /**
   * Get a UUID for this location.
   *
   * @return string
   *   A string representing a UUID.
   */
  public function getUuid(): string;

  /**
   * Get the version to use for the geojson shapefiles.
   *
   * @return int|string
   *   Returns the year component of the valid_on date for expired locations,
   *   or the string 'current'.
   */
  public function getGeoJsonVersion(): string;

  /**
   * Check if we have a geojson file for this location.
   *
   * @return bool
   *   TRUE if a geojson file is there, FALSE otherwise.
   */
  public function hasGeoJsonFile(): bool;

  /**
   * Get an array with geojson specific location data.
   *
   * @return array
   *   An array with geojson specific location data.
   */
  public function getGeoJsonLocationData(): array;

}
