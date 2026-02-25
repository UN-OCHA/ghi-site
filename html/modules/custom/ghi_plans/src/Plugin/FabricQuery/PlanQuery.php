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
    $plan = $this->getObjectFromStorage($plan_id, Plan::getObjectStorageKey());
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

    // Add the reporting periods.
    $plan_data->planReportingPeriods = array_map(fn ($period) => new PlanReportingPeriod($period), $data['planReportingPeriods'] ?? []);
    $this->addObjectCollectionToStorage($plan_data->planReportingPeriods, PlanReportingPeriod::getObjectCollectionStorageKey(), 'PlanId');

    $plan = new Plan($plan_data);
    $this->addObjectToStorage($plan);
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
    $plan_ids = array_unique($plan_ids);
    $plans = $this->getObjectsFromStorage($plan_ids, Plan::getObjectStorageKey());
    if (count($plans) == count($plan_ids)) {
      return $plans;
    }
    $plan_ids = array_diff($plan_ids, array_keys($plans));

    // Get the plan data.
    sort($plan_ids);
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
      // Add the reporting periods.
      $item->planReportingPeriods = array_map(fn ($period) => new PlanReportingPeriod($period), array_filter($data['planReportingPeriods'] ?? [], fn ($period) => $period->PlanId == $item->Id));
      $this->addObjectCollectionToStorage($item->planReportingPeriods, PlanReportingPeriod::getObjectCollectionStorageKey(), 'PlanId');

      $plans[$item->Id] = new Plan($item);
      $this->addObjectToStorage($plans[$item->Id]);
    }
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
    $reporting_periods = $this->getObjectCollectionFromStorage(PlanReportingPeriod::getObjectCollectionStorageKey(), 'PlanId', $plan_id);
    if ($reporting_periods) {
      return $reporting_periods;
    }

    $items = $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
      ->setFilter('PlanId', $plan_id)
      ->execute();

    $reporting_periods = array_map(fn ($item): PlanReportingPeriod => new PlanReportingPeriod($item), $items);
    $this->addObjectCollectionToStorage($reporting_periods, PlanReportingPeriod::getObjectCollectionStorageKey(), 'PlanId');
    return $reporting_periods;
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
  public function getPlanTypeByName($name): ?PlanType {
    foreach ($this->getPlanTypes() as $plan_type) {
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
  public function getPlanCostingTypeByName($name): ?PlanCostingType {
    foreach ($this->getPlanCostingTypes() as $plan_costing_type) {
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
  public function lookupCountry(string $name): ?Country {
    $country = $this->getCountryQuery()->getCountryByName($name);
    if (!$country instanceof Country) {
      return NULL;
    }
    $this->addObjectToStorage($country);
    return $country;
  }

}
