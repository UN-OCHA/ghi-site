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
  id: 'locations',
  label: new TranslatableMarkup('Locations query'),
)]
class LocationsQuery extends FabricQueryBase {

  use StringTranslationTrait;

  const MAX_LEVEL = 5;

  /**
   * Get a location.
   *
   * @param int $location_id
   *   A location id known to the API.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location object.
   */
  public function getLocation($location_id, $max_level = 1): ?Location {
    $items = $this->fabricClient->createQuery('locations', Location::GRAPHQL_DIMENSION_ITEMS, NULL, 1)
      ->setFilter('Id', $location_id)
      ->execute();
    return count($items) == 1 ? new Location($items[0]) : NULL;
  }

}
