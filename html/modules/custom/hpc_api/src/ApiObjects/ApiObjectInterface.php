<?php

namespace Drupal\hpc_api\ApiObjects;

/**
 * Interface for API objects.
 */
interface ApiObjectInterface {

  /**
   * Get the id of an entity object.
   *
   * @return int
   *   The id of the entity object.
   */
  public function id();

  /**
   * Get the raw data for the object, as returned by the API.
   *
   * @return object
   *   The raw data object.
   */
  public function getRawData();

  /**
   * Get the items used for GraphQL queries.
   *
   * @return array
   *   An array with items used for GraphQL queries.
   */
  public static function getGraphQlItems();

  /**
   * Represent this as an array.
   *
   * @return array
   *   The mapped data as an array.
   */
  public function toArray();

}
