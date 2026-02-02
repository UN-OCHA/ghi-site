<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'locations' fabric query.
 */
#[FabricQuery(
  id: 'location',
  label: new TranslatableMarkup('Locations query'),
)]
class LocationQuery extends FabricQueryBase {

  use StringTranslationTrait;

  const MAX_LEVEL = 5;

  /**
   * Get a location.
   *
   * @param int $location_id
   *   A location id known to the API.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location object.
   */
  public function getLocation($location_id): ?Location {
    $items = $this->fabricClient->createQuery('locations', Location::GRAPHQL_DIMENSION_ITEMS, NULL, 1)
      ->setFilter('Id', $location_id)
      ->execute();
    $item = count($items) == 1 ? reset($items) : NULL;
    return $item ? new Location($item) : NULL;
  }

  /**
   * Get all locations of the country.
   *
   * @param int $country_id
   *   The country id.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   * @param int[] $limit_location_ids
   *   Optional: An array of location ids to limit the result to.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   An array of location objects keyed by the location id.
   */
  public function getLocationsForCountry(int $country_id, int $max_level, $limit_location_ids = NULL) {

    return [];
  }

}
