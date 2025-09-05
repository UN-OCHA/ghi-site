<?php

namespace Drupal\ghi_geojson;

interface GeoJsonLocationInterface {

  /**
   * Get the iso3 code.
   *
   * @return string|null
   *   The iso3 code or NULL if not found.
   */
  public function getIso3();

  /**
   * Get the admin level.
   *
   * @return int
   *   The admin level.
   */
  public function getAdminLevel();

  /**
   * Get the pcode.
   *
   * @return string
   *   The pcode.
   */
  public function getPcode();

  /**
   * Get a UUID for this location.
   *
   * @return string
   *   A string representing a UUID.
   */
  public function getUuid();

  /**
   * Get the version to use for the geojson shapefiles.
   *
   * @return int|string
   *   Returns the year component of the valid_on date for expired locations,
   *   or the string 'current'.
   */
  public function getGeoJsonVersion();

}
