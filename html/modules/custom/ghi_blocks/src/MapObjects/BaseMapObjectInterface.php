<?php

namespace Drupal\ghi_blocks\MapObjects;

/**
 * Interface for map objects.
 */
interface BaseMapObjectInterface {

  /**
   * Construct a new map object.
   *
   * @param int $id
   *   The id of the map object.
   * @param string $name
   *   The name of the map object.
   * @param int[] $location_ids
   *   The location ids of the map object.
   * @param array $data
   *   Additional data for the map object.
   */
  public function __construct($id, $name, array $location_ids, array $data = []);

  /**
   * Get the id of the map object.
   *
   * @return int
   *   The id of the map object.
   */
  public function id();

  /**
   * Get the name of the map object.
   *
   * @return string
   *   The name of the map object.
   */
  public function getName();

  /**
   * Get the location ids for the map object.
   *
   * @return int[]
   *   An array of location ids.
   */
  public function getLocationIds();

}
