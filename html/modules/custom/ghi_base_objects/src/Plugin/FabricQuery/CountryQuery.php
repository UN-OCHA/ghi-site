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
    $items = $this->fabricClient->createQuery('locations', Country::getGraphQlItems())
      ->setFilter('AdminLevel', 0)
      ->setOrderBy(['Name' => 'ASC'])
      ->execute();
    $this->countries = $this->buildResultObjects($items, Country::class);
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
