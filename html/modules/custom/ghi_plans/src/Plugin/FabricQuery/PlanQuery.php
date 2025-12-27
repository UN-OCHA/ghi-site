<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery;
use Drupal\ghi_plans\ApiObjects\Plan;
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
    // Get the plan.
    $payload = "
      plans (filter:  {
        Id:  {
          eq: {$plan_id}
        }
      }) {
        items { " . Plan::GRAPHQL_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    $plan_data = $data->plans->items[0] ?? NULL;
    if ($plan_data === NULL) {
      return NULL;
    }

    // Lookup the focus country.
    $plan_data->FocusCountry = $plan_data->FocusedLocationName ? $this->lookupCountry($plan_data->FocusedLocationName) : NULL;
    $plan_data->PlanType = $plan_data->PlanType ? $this->getPlanTypeByName($plan_data->PlanType) : NULL;
    $plan_data->PlanCosting = $plan_data->PlanCosting ? $this->getPlanCostingTypeByName($plan_data->PlanCosting) : NULL;

    return new Plan($plan_data);
  }

  /**
   * Get all plans for the given year.
   *
   * @param int $year
   *   The year.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getPlansByYear(int $year) {
    // Get the plan.
    $payload = "
      plans (filter:  {
        period: {
          PeriodType: { eq: \"Year\" }
          and: [{ CalendarYear: { eq: {$year} } }]
        }
      }) {
        items { " . Plan::GRAPHQL_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    return $this->buildResultObjectsFromData($data, 'plans', Plan::class);
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
    return $this->countryQuery()->getCountryByName($name);
  }

  /**
   * Get the country query.
   *
   * @return \Drupal\ghi_base_objects\Plugin\FabricQuery\CountryQuery
   *   The country query.
   */
  public static function countryQuery(): CountryQuery {
    /** @var \Drupal\hpc_api\Query\FabricQueryManager $fabric_query_manager */
    $fabric_query_manager = \Drupal::service('plugin.manager.fabric_query_manager');
    return $fabric_query_manager->createInstance('country');
  }

}
