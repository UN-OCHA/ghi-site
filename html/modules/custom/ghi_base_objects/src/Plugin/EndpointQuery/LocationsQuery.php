<?php

namespace Drupal\ghi_base_objects\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for locations.
 *
 * @EndpointQuery(
 *   id = "locations_query",
 *   label = @Translation("Locations query"),
 *   endpoint = {
 *     "api_key" = "location/nested/{location_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "scopes" = "locations",
 *     }
 *   }
 * )
 */
class LocationsQuery extends EndpointQueryBase {

  use StringTranslationTrait;

  const MAX_LEVEL = 5;

  /**
   * Get a country.
   *
   * @param int $country_id
   *   A country id known to the API.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   * @param bool $include_expired
   *   Include expired locations.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A country location.
   */
  public function getCountry($country_id, $max_level = NULL, $include_expired = TRUE) {
    $this->setPlaceholder('location_id', $country_id);
    $this->endpointQuery->setEndpointArguments(array_filter([
      'maxLevel' => $max_level ?? self::MAX_LEVEL,
      'includeExpired' => $include_expired ? 'true' : NULL,
    ], function ($item) {
      return $item !== NULL;
    }));
    $data = $this->getData();
    return $data ? new Location($data) : NULL;
  }

  /**
   * Get a location inside a country.
   *
   * @param int $country_id
   *   A country id known to the API.
   * @param int $location_id
   *   A location id known to the API.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location.
   */
  public function getCountryLocation($country_id, $location_id) {
    // First get the location.
    $this->setPlaceholder('location_id', $location_id);
    $this->endpointQuery->setEndpointArguments(array_filter([
      'maxLevel' => 0,
      'includeExpired' => 'true',
    ], function ($item) {
      return $item !== NULL;
    }));
    $data = $this->getData();
    if (empty($data)) {
      return NULL;
    }
    $location = new Location($data);
    // Then get the country and make a simple sanity check.
    $country = $this->getCountry($country_id, 0);
    if (!$country || !str_starts_with($location->getPcode(), $country->getPcode())) {
      return NULL;
    }
    $location->setParentCountry($country);
    return $location;
  }

  /**
   * Get the coordinations for all locations of the country.
   *
   * @param int $country_id
   *   The country id.
   * @param int $max_level
   *   A maximum level of nested locations to retrieve.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   An array of location objects keyed by the location id.
   */
  public function getCountryLocations($country_id, $max_level = self::MAX_LEVEL) {
    $cache_key = $this->getCacheKey([
      'country_id' => $country_id,
      'max_level' => $max_level,
    ]);
    $locations = $this->getCache($cache_key);
    if ($locations) {
      return $locations;
    }

    $country = $this->getCountry($country_id, $max_level);
    if (!$country || empty($country->children) || !is_array($country->children)) {
      $this->setCache($cache_key, []);
      return [];
    }

    // Make it a flat array.
    $flat_locations = $this->flattenLocationArray($country->children);
    $locations = [];
    // Now look at each location and prepare the full set of location data.
    foreach ($flat_locations as $item) {
      if (empty($item->latitude) || empty($item->longitude)) {
        // Skip locations without full map data.
        continue;
      }
      $locations[$item->id] = new Location($item);
      $locations[$item->id]->setParentCountry($country);
      $this->setCacheTags($locations[$item->id]->getCacheTags());
    }

    // We filter the locations for empty coordinates and for admin level 0.
    $locations = array_filter($locations, function (Location $location) {
      return !empty($location->latLng[0]) && !empty($location->latLng[1]) && !$location->isCountry();
    });
    $this->setCache($cache_key, $locations);
    return $locations;
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
  private function flattenLocationArray(array $array, $child_key = 'children', ?array &$flat_locations = NULL) {
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
