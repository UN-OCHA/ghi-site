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
   * Get a location by id.
   *
   * @param int $location_id
   *   A location id to load.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location object.
   */
  public function getLocation(int $location_id): ?Location {
    $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems(), NULL, 1)
      ->setFilter('Id', $location_id)
      ->execute();
    $item = count($items) == 1 ? reset($items) : NULL;
    return $item ? new Location($item) : NULL;
  }

  /**
   * Get locations by id.
   *
   * @param int[] $location_ids
   *   The location ids to load.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   An array of location objects.
   */
  public function getLocations($location_ids): array {
    if (empty($location_ids)) {
      return [];
    }
    if (count($location_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      // We need to do multiple queries.
      return $this->doChunkedQuery($location_ids, fn ($ids): array => $this->getLocations($ids));
    }
    $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems())
      ->setFilter('Id', $location_ids)
      ->execute();
    return $this->buildResultObjects($items, Location::class);
  }

  /**
   * Get all locations of the country.
   *
   * @param int $country_id
   *   The country id.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   An array of location objects keyed by the location id.
   */
  public function getLocationsForCountry(int $country_id, int $max_level = 3) {
    $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems())
      ->setFilter('CountryId', $country_id)
      ->setFilter('AdminLevel', range(1, $max_level))
      ->setFilter('RecordStatus', 'Active')
      ->execute();
    return $this->buildResultObjects($items, Location::class);
  }

}
