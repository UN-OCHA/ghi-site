<?php

namespace Drupal\ghi_base_objects\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\ApiObjects\Location;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for country locations.
 *
 * @EndpointQuery(
 *   id = "country_query",
 *   label = @Translation("Country query"),
 *   endpoint = {
 *     "api_key" = "location",
 *     "version" = "v2",
 *   }
 * )
 */
class CountryQuery extends EndpointQueryBase {

  use StringTranslationTrait;

  /**
   * Get country location objects for all countries.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location[]
   *   An array of country locations.
   */
  public function getCountries() {
    $cache_key = 'locations';
    $countries = $this->cache($cache_key);
    if ($countries) {
      return $countries;
    }
    $data = $this->getData();
    if (empty($data)) {
      return [];
    }

    $countries = [];
    foreach ($data as $item) {
      $countries[$item->id] = new Location($item);
    }
    $this->cache($cache_key, $countries);
    return $countries;
  }

  /**
   * Get country location objects for all countries.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Location|null
   *   A location object or NULL.
   */
  public function getCountry($country_id) {
    $countries = $this->getCountries();
    return array_key_exists($country_id, $countries) ? $countries[$country_id] : NULL;
  }

}
