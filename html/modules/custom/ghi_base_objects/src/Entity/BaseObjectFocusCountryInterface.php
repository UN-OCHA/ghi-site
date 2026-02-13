<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_base_objects\Entity\Country as EntityCountry;

/**
 * Provides an interface for defining base object that have a focus country.
 *
 * @ingroup ghi_base_objects
 */
interface BaseObjectFocusCountryInterface {

  /**
   * Get the focus country for the plan.
   *
   * @return \Drupal\ghi_base_objects\Entity\Country|null
   *   The country base object or NULL.
   */
  public function getFocusCountry(): ?EntityCountry;

  /**
   * Get the focus country override for the plan.
   *
   * @return object|null
   *   A latLng object or NULL.
   */
  public function getFocusCountryOverride();

  /**
   * Get the focus country map location for the plan.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   An object describing the map location or NULL.
   */
  public function getFocusCountryMapLocation(?Country $default_country = NULL);

}
