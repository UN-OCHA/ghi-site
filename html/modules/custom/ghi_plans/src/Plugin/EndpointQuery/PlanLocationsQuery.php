<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan locations.
 *
 * @EndpointQuery(
 *   id = "plan_locations_query",
 *   label = @Translation("Plan locations query"),
 *   endpoint = {
 *     "public" = "public/plan/{plan_id}",
 *     "version" = "v2",
 *     "query" = {
 *       "scopes" = "locations",
 *     }
 *   }
 * )
 */
class PlanLocationsQuery extends EndpointQueryBase {

  use StringTranslationTrait;

  /**
   * Get the country for a plan.
   *
   * @return object
   *   A country object as returned by the API.
   */
  public function getCountry() {
    $data = $this->getData();
    $country_candidates = !empty($data->locations) ? array_filter($data->locations, function ($location) {
      return $location->adminLevel == 0;
    }) : NULL;
    if (empty($country_candidates)) {
      return NULL;
    }
    $country = reset($country_candidates);
    return $country;
  }

}
