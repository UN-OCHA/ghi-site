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
   * The maximum number of location ids to request in one Fabric query.
   *
   * Manual testing indicates that the real limit on the fabric size is 2086,
   * but let's stay considerably lower just in case.
   */
  private const MAX_ID_LOOKUP_BATCH_SIZE = 1500;

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

    $locations = [];
    foreach (array_chunk($location_ids, self::MAX_ID_LOOKUP_BATCH_SIZE) as $location_id_batch) {
      $items = $this->fabricClient->createQuery('locations', Location::getGraphQlItems())
        ->setFilter('Id', $location_id_batch)
        ->setFilter('AdminLevel', 'NOT NULL')
        ->execute() ?: [];
      $locations += $this->buildResultObjects($items, Location::class);
    }
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
