<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery;

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
    $locations = $this->getLocationsById([$location_id]);
    return !empty($locations) ? reset($locations) : NULL;
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
  public function getLocationsById(array $location_ids): array {
    if (empty($location_ids)) {
      return [];
    }

    $location_ids = array_unique($location_ids);
    sort($location_ids);

    if (count($location_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      // We need to do multiple queries.
      return $this->doChunkedQuery($location_ids, fn ($ids): array => $this->getLocationsById($ids));
    }
    $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems())
      ->setFilter('Id', $location_ids)
      ->setFilter('AdminLevel', 'NOT NULL')
      ->execute() ?: [];
    $locations = $this->buildResultObjects($items, Location::class);
    return $locations;
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
  public function getLocationsForCountry(int $country_id, int $max_level = 3): array {
    $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems())
      ->setFilter('CountryId', $country_id)
      ->setFilter('AdminLevel', range(1, $max_level))
      ->setFilter('RecordStatus', 'Active')
      ->execute() ?: [];
    $locations = $this->buildResultObjects($items, Location::class);
    return $locations;
  }

}
