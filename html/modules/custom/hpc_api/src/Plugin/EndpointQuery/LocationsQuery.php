<?php

namespace Drupal\hpc_api\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_api\ApiObjects\Location;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides a query plugin for locations.
 *
 * @EndpointQuery(
 *   id = "locations_query",
 *   label = @Translation("Locations query"),
 *   endpoint = {
 *     "api_key" = "location/nested/{country_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "scopes" = "locations",
 *     }
 *   }
 * )
 */
class LocationsQuery extends EndpointQueryBase {

  use StringTranslationTrait;
  use SimpleCacheTrait;

  const MAX_LEVEL = 3;

  /**
   * Get the country locations.
   *
   * @param int $country_id
   *   A country object as returned by the API.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   * @param bool $include_expired
   *   Include expired locations.
   *
   * @return object
   *   An unprocessed response object from the API.
   */
  private function getCountryLocationData($country_id, $max_level = self::MAX_LEVEL, $include_expired = TRUE) {
    $this->setPlaceholder('country_id', $country_id);
    $this->endpointQuery->setEndpointArguments(array_filter([
      'maxLevel' => $max_level,
      'includeExpired' => $include_expired ? 'true' : NULL,
    ]));
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }
    return $data ?? NULL;
  }

  /**
   * Get the coordinations for all locations of the country.
   *
   * @param object $country
   *   An object that needs to have the id property set to the countries id.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   *
   * @return \Drupal\hpc_api\ApiObjects\Location[]
   *   An array of location objects keyed by the location id.
   */
  public function getCountryLocations($country, $max_level = self::MAX_LEVEL) {
    $cache_key = $this->getCacheKey([
      'country_id' => $country->id,
      'max_level' => $max_level,
    ]);
    $locations = $this->cache($cache_key);
    if ($locations) {
      return $locations;
    }

    $data = $this->getCountryLocationData($country->id, $max_level);
    if (empty($data) || empty($data->children) || !is_array($data->children)) {
      return $this->cache($cache_key, []);
    }

    // Make it a flat array.
    $flat_locations = $this->flattenLocationArray($data->children);
    $locations = [];
    // Now look at each location and prepare the full set of location data.
    foreach ($flat_locations as $item) {
      if (empty($item->latitude) || empty($item->longitude)) {
        // Skip locations without full map data.
        continue;
      }
      $locations[$item->id] = new Location($item);
    }

    // We filter the locations for empty coordinates and for admin level 0.
    $locations = array_filter($locations, function ($location) {
      return !empty($location->latLng[0]) && !empty($location->latLng[1]) && $location->admin_level != 0;
    });
    return $this->cache($cache_key, $locations);
  }

  /**
   * Transform an hierarchical location array from the API into a flat array.
   *
   * @param array $array
   *   An array of location objects. Each object can contain a child property
   *   listing sublocations. If so, then we drill into this recursively.
   * @param string $child_key
   *   The name of the child property holding sublocations.
   * @param array $flat_locations
   *   The carry over locations for recursive calls.
   *
   * @return array
   *   A flat array of location objects.
   */
  private function flattenLocationArray(array $array, $child_key = 'children', array &$flat_locations = NULL) {
    if ($flat_locations === NULL) {
      $flat_locations = [];
    }
    foreach ($array as $value) {
      if (!is_object($value)) {
        continue;
      }
      $location = clone $value;
      unset($location->$child_key);
      $flat_locations[$location->id] = $location;
      if (!property_exists($value, $child_key) || empty($value->$child_key) || !is_array($value->$child_key)) {
        continue;
      }
      $this->flattenLocationArray($value->$child_key, $child_key, $flat_locations);
    }
    return $flat_locations;
  }

}
