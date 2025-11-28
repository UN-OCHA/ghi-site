<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
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
   * The countries.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Country[]|null
   */
  protected $countries = NULL;

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
      {
        plans (filter:  {
          HpcId:  {
            eq: {$plan_id}
          }
        }) {
          items { " . Plan::GRAPHQL_ITEMS . " }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $plan_data = $data->plans->items[0] ?? NULL;
    if ($plan_data === NULL) {
      return NULL;
    }

    // Get the plan type.
    $plan_data->PlanTypes = $this->getPlanTypes($plan_data->Id);
    // Get the plan costing type.
    $plan_data->PlanCostingTypes = $this->getPlanCostingTypes($plan_data->Id);
    // Lookup the focus country.
    $plan_data->FocusCountry = $plan_data->FocusedLocationName ? $this->lookupCountry($plan_data->FocusedLocationName) : NULL;

    return new Plan($plan_data);
  }

  /**
   * Get the plan cvategory relationship data for the given id.
   *
   * @param int $id
   *   The internal id of the plan object. This is NOT the plan id that is
   *   exposed publicly.
   *
   * @return object[]
   *   An array of objects.
   */
  private function getPlanPeriodRelationships(int $id) {
    $plan_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PLAN);
    $period_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PERIOD);
    return $this->getRelationshipItems($plan_entity_type->id(), $period_entity_type->id(), $id);
  }

  /**
   * Get the plan cvategory relationship data for the given id.
   *
   * @param int $id
   *   The internal id of the plan object. This is NOT the plan id that is
   *   exposed publicly.
   *
   * @return object[]
   *   An array of objects.
   */
  private function getPlanCategoryRelationships(int $id) {
    $category_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_CATEGORY);
    $plan_entity_type = $this->getEntityTypeByName(self::ENTITY_TYPE_NAME_PLAN);
    return $this->getRelationshipItems($category_entity_type->id(), $plan_entity_type->id(), NULL, $id);
  }

  /**
   * Get the plan year.
   *
   * @param int $id
   *   The internal id of the plan object. This is NOT the plan id that is
   *   exposed publicly.
   *
   * @return int|null
   *   The plan year or NULL.
   */
  protected function getPlanYear(int $id): ?int {
    $this->fetchPlanYears();
    $plan_period_relationships = $this->getPlanPeriodRelationships($id);
    $plan_years = array_filter(array_map(fn($item) => $this->planYears[$item->ToId] ?? NULL, $plan_period_relationships));
    return !empty($plan_years) ? reset($plan_years)->getYear() : NULL;
  }

  /**
   * Get the plan types for the given id.
   *
   * @param int $id
   *   The internal id of the plan object. This is NOT the plan id that is
   *   exposed publicly.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanType[]
   *   An array of plan type objects.
   */
  protected function getPlanTypes(int $id) {
    $plan_type_relationships = $this->getPlanCategoryRelationships($id);
    return array_filter(array_map(fn($item): ?PlanType => $this->getPlanTypeById($item->FromId), $plan_type_relationships));
  }

  /**
   * Get the plan costing types for the given id.
   *
   * @param int $id
   *   The internal id of the plan object. This is NOT the plan id that is
   *   exposed publicly.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanType[]
   *   An array of plan costing type objects.
   */
  protected function getPlanCostingTypes($id) {
    $plan_type_relationships = $this->getPlanCategoryRelationships($id);
    return array_filter(array_map(fn($item): ?PlanType => $this->getPlanCostingTypeById($item->FromId), $plan_type_relationships));
  }

  /**
   * Retrieve the plan types from the API.
   */
  protected function fetchPlanTypes(): void {
    if ($this->planTypes !== NULL) {
      return;
    }
    $items = $this->getCategoryItems(self::PLAN_TYPE_CATEGORY_NAME);
    $this->planTypes = array_map(fn($item): PlanType => new PlanType($item), $items);
  }

  /**
   * Retrieve the plan types from the API.
   */
  protected function fetchPlanCostingTypes(): void {
    if ($this->planCostingTypes !== NULL) {
      return;
    }
    $items = $this->getCategoryItems(self::PLAN_COSTING_TYPE_CATEGORY_NAME);
    $this->planCostingTypes = array_map(fn($item): PlanCostingType => new PlanCostingType($item), $items);
  }

  /**
   * Retrieve the plan years from the API.
   */
  protected function fetchCountries(): void {
    if ($this->countries !== NULL) {
      return;
    }
    $payload = "
      {
        locations (filter: { AdminLevel: { eq: 0 } }) {
          items {
            Id
            Name
            ISO3
            Latitude
            Longitude
          }
        }
      }";
    $data = $this->fabricQuery->query($payload);
    $items = $data->locations->items;
    $ids = array_map(fn($item) => $item->Id, $items);
    $items = array_combine($ids, $items);
    $this->countries = array_map(fn($item): Country => new Country($item), $items);
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
    $this->fetchCountries();
    foreach ($this->countries as $country) {
      if ($country->getName() == $name) {
        return $country;
      }
    }
    return NULL;
  }

  /**
   * Get a plan type object by its id.
   *
   * @param int $id
   *   The if of the plan type.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanType|null
   *   The plan type or NULL.
   */
  protected function getPlanTypeById(int $id): ?PlanType {
    $this->fetchPlanTypes();
    return $this->planTypes[$id] ?? NULL;
  }

  /**
   * Get a plan type object by its id.
   *
   * @param int $id
   *   The if of the plan type.
   *
   * @return Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   *   The plan type or NULL.
   */
  protected function getPlanCostingTypeById(int $id): ?PlanCostingType {
    $this->fetchPlanCostingTypes();
    return $this->planCostingTypes[$id] ?? NULL;
  }

  /**
   * Get a plan type object by its id.
   *
   * @param string $name
   *   The name of the plan type.
   *
   * @return Drupal\hpc_api\ApiObjects\Types\PlanType|null
   *   The plan type or NULL.
   */
  protected function getPlanTypeByName(string $name): ?PlanType {
    $this->fetchPlanTypes();
    foreach ($this->planTypes as $plan_type) {
      if ($plan_type->getName() == $name) {
        return $plan_type;
      }
    }
    return NULL;
  }

  /**
   * Get a plan costing type object by its id.
   *
   * @param string $name
   *   The name of the plan costing type.
   *
   * @return Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   *   The plan costing type or NULL.
   */
  protected function getPlanCostingTypeByName(string $name): ?PlanCostingType {
    $this->fetchPlanCostingTypes();
    foreach ($this->planCostingTypes as $plan_costing_type) {
      if ($plan_costing_type->getName() == $name) {
        return $plan_costing_type;
      }
    }
    return NULL;
  }

}
