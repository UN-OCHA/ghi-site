<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\Types\PlanCostingType;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'plan' fabric query.
 */
#[FabricQuery(
  id: 'plan',
  label: new TranslatableMarkup('Plan query'),
)]
class PlanQuery extends FabricQueryBase {

  use PlanQueryTrait;

  /**
   * The plan types.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\PlanType[]|null
   */
  protected $planTypes = NULL;

  /**
   * The plan types.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\PlanCostingType[]|null
   */
  protected $planCostingTypes = NULL;

  /**
   * Get a plan by its id.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan|null
   *   The plan object or NULL if not found.
   */
  public function getPlan(int $plan_id): ?Plan {
    $cache_key = $this->getCacheKey(['id' => $plan_id]);
    $plan = $this->getCache($cache_key);
    if ($plan) {
      return $plan;
    }
    // Get the plan data.
    $items = $this->fabricClient->createQuery('plans', Plan::GRAPHQL_DIMENSION_ITEMS, NULL, 1)
      ->setFilter('Id', $plan_id)
      ->execute();

    $plan_data = count($items) ? reset($items) : NULL;
    if ($plan_data === NULL) {
      return NULL;
    }

    // Lookup the focus country.
    $plan_data->FocusCountry = $plan_data->FocusedLocationName ? $this->lookupCountry($plan_data->FocusedLocationName) : NULL;
    $plan_data->PlanType = $plan_data->PlanType ? $this->getPlanTypeByName($plan_data->PlanType) : NULL;
    $plan_data->PlanCosting = $plan_data->PlanCosting ? $this->getPlanCostingTypeByName($plan_data->PlanCosting) : NULL;

    $plan = new Plan($plan_data);
    $this->setCache($cache_key, $plan);
    return $plan;
  }

  /**
   * Get the plan type by name.
   *
   * @param string $name
   *   The name of the plan type to get.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanType|null
   *   The plan type object or NULL.
   */
  protected function getPlanTypeByName($name): ?PlanType {
    $this->fetchPlanTypes();
    foreach ($this->planTypes as $plan_type) {
      if ($plan_type->getName() == $name) {
        return $plan_type;
      }
    }
    return NULL;
  }

  /**
   * Get the plan costing type by name.
   *
   * @param string $name
   *   The name of the plan costing type to get.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   *   The plan costing type object or NULL.
   */
  protected function getPlanCostingTypeByName($name): ?PlanCostingType {
    $this->fetchPlanCostingTypes();
    foreach ($this->planCostingTypes as $plan_costing_type) {
      if ($plan_costing_type->getName() == $name) {
        return $plan_costing_type;
      }
    }
    return NULL;
  }

  /**
   * Lookup a country by name.
   *
   * @param string $name
   *   The country name to look for.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   The country object or NULL.
   */
  protected function lookupCountry(string $name): ?Country {
    return $this->getCountryQuery()->getCountryByName($name);
  }

}
