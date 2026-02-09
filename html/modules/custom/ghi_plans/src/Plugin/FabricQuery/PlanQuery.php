<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
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
    $queries = [
      $this->fabricClient->createQuery('plans', Plan::getGraphQlItems(), NULL, 1)
        ->setFilter('Id', $plan_id),
      $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
        ->setFilter('PlanId', $plan_id),
    ];
    $data = $this->fabricClient->executeMultiple($queries);

    $plan_data = count($data['plans']) ? reset($data['plans']) : NULL;
    if ($plan_data === NULL) {
      return NULL;
    }

    // Lookup the focus country.
    $plan_data->FocusCountry = $plan_data->FocusedLocationName ? $this->lookupCountry($plan_data->FocusedLocationName) : NULL;
    $plan_data->PlanType = $plan_data->PlanType ? $this->getPlanTypeByName($plan_data->PlanType) : NULL;
    $plan_data->PlanCosting = $plan_data->PlanCosting ? $this->getPlanCostingTypeByName($plan_data->PlanCosting) : NULL;
    $plan_data->ReportingPeriods = $data['planReportingPeriods'] ?? [];

    $plan = new Plan($plan_data);
    $this->setCache($cache_key, $plan);
    return $plan;
  }

  /**
   * Get plans by id.
   *
   * @param int[] $plan_ids
   *   The plan ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getPlansById(array $plan_ids): array {
    sort($plan_ids);
    $cache_key = $this->getCacheKey(['ids' => $plan_ids]);
    $plans = $this->getCache($cache_key);
    if ($plans) {
      return $plans;
    }

    // Get the plan data.
    $queries = [
      $this->fabricClient->createQuery('plans', Plan::getGraphQlItems())
        ->setFilter('Id', $plan_ids),
      $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
        ->setFilter('PlanId', $plan_ids),
    ];
    $data = $this->fabricClient->executeMultiple($queries);
    if (empty($data['plans'])) {
      return [];
    }

    $plans = [];
    foreach ($data['plans'] as $item) {
      $item->FocusCountry = $item->FocusedLocationName ? $this->lookupCountry($item->FocusedLocationName) : NULL;
      $item->PlanType = $item->PlanType ? $this->getPlanTypeByName($item->PlanType) : NULL;
      $item->PlanCosting = $item->PlanCosting ? $this->getPlanCostingTypeByName($item->PlanCosting) : NULL;
      $item->ReportingPeriods = array_filter($data['planReportingPeriods'] ?? [], fn ($period) => $period->PlanId == $item->Id);
      $plans[$item->Id] = new Plan($item);
    }
    $this->setCache($cache_key, $plans);
    return $plans;
  }

  /**
   * Get the reporting periods for the plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of plan reporting periods.
   */
  public function getPlanReportingPeriods(int $plan_id) {
    $cache_key = $this->getCacheKey(['id' => $plan_id]);
    $reporting_periods = $this->getCache($cache_key);
    if ($reporting_periods) {
      return $reporting_periods;
    }
    $items = $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
      ->setFilter('PlanId', $plan_id)
      ->execute();

    $reporting_periods = array_map(fn ($item) => new PlanReportingPeriod($item), $items);
    return $this->cache($cache_key, $reporting_periods);
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
