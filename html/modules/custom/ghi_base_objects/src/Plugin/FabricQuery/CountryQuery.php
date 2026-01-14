<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'country' fabric query.
 */
#[FabricQuery(
  id: 'country',
  label: new TranslatableMarkup('Country query'),
)]
class CountryQuery extends FabricQueryBase {

  /**
   * The countries.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Country[]|null
   */
  protected $countries = NULL;

  /**
   * Get country location objects for all countries.
   */
  private function fetchCountries() {
    if ($this->countries !== NULL) {
      return;
    }
    $payload = '
      locations (
        filter: { AdminLevel: { eq: 0 } },
        first: 1000,
        orderBy: { Name: ASC }
      ) {
        items { ' . Country::GRAPHQL_DIMENSION_ITEMS . ' }
      }';
    $data = $this->fabricQuery->query($payload);
    $this->countries = $this->buildResultObjectsFromData($data, 'locations', Country::class);
  }

  /**
   * Get a country by id.
   *
   * @param int $country_id
   *   The country id.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   A country object or NULL.
   */
  public function getCountry(int $country_id): ?Country {
    $this->fetchCountries();
    return $this->countries[$country_id] ?? NULL;
  }

  /**
   * Get a country by name.
   *
   * @param string $name
   *   The name of the country to search.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   A country object or NULL.
   */
  public function getCountryByName(string $name): ?Country {
    $this->fetchCountries();
    foreach ($this->countries as $country) {
      if ($country->getName() == $name) {
        return $country;
      }
    }
    return NULL;
  }

}
