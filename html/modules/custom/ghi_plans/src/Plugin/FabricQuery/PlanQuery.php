<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Attachments\CostAttachment;
use Drupal\ghi_plans\ApiObjects\Plan;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\Types\PlanCostingType;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Traits\EndpointQueryTrait;

/**
 * Plugin implementation of the 'plan' fabric query.
 */
#[FabricQuery(
  id: 'plan',
  label: new TranslatableMarkup('Plan query'),
)]
class PlanQuery extends FabricQueryBase {

  use PlanQueryTrait;
  use EndpointQueryTrait;

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
    $plans = $this->getPlansById([$plan_id]);
    return !empty($plans) ? reset($plans) : NULL;
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
    $cached_plans = $this->objectStore->getObjects($plan_ids, Plan::getObjectStorageKey());
    if (count($cached_plans) == count($plan_ids)) {
      return $cached_plans;
    }
    $missing_plan_ids = array_diff($plan_ids, array_keys($cached_plans));

    // Get the plan data.
    sort($missing_plan_ids);
    $queries = [
      $this->fabricClient->createQuery('plans', Plan::getGraphQlItems())
        ->setFilter('Id', $missing_plan_ids),
      $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
        ->setFilter('PlanId', $missing_plan_ids),
    ];
    $data = $this->fabricClient->executeMultiple($queries);
    if (empty($data['plans'])) {
      return $cached_plans;
    }

    $fetched_plans = [];
    foreach ($data['plans'] as $item) {
      // Add the reporting periods.
      $item->planReportingPeriods = array_map(fn ($period) => new PlanReportingPeriod($period), array_filter($data['planReportingPeriods'] ?? [], fn ($period) => $period->PlanId == $item->Id));
      $this->objectStore->addObjectCollection($item->planReportingPeriods, PlanReportingPeriod::getObjectStorageKey(), 'PlanId');

      $fetched_plans[$item->Id] = new Plan($item);
    }
    $this->objectStore->addObjects($fetched_plans);
    $plans = $cached_plans + $fetched_plans;
    $ordered_plans = array_replace(array_fill_keys($plan_ids, NULL), $plans);
    return array_intersect_key($ordered_plans, $plans);
  }

  /**
   * Get plans by year.
   *
   * @param int $year
   *   The year for which to fetch plans.
   * @param bool $current_version
   *   Whether to restrict to plans with a current version.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getPlansByYear(int $year, bool $current_version = TRUE): array {
    $plans = $this->objectStore->getObjectCollection(Plan::getObjectStorageKey(), 'year', $year);
    if (!empty($plans)) {
      return $plans;
    }
    $items = $this->fabricClient->createQuery('plans', ['Id'])
      ->setFilters(array_filter([
        'period' => [
          'PeriodType' => 'Year',
          'CalendarYear' => $year,
        ],
        'IsLegacyCurrentVersion' => $current_version ? TRUE : NULL,
      ]))
      ->execute() ?: [];
    $plan_ids = $this->extractIdsFromRawData($items);
    $plans = $this->getPlansById($plan_ids);
    if (count($plans) == count($plan_ids)) {
      $this->objectStore->addObjectCollection($plans, Plan::getObjectStorageKey(), 'year');
    }
    return $plans;
  }

  /**
   * Get plans by id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getAllPlans(): array {
    $retrieved = &drupal_static(__FUNCTION__, FALSE);
    if ($retrieved) {
      return $this->objectStore->getAllObjects(Plan::getObjectStorageKey());
    }
    // Get the plan data.
    $queries = [
      $this->fabricClient->createQuery('plans', Plan::getGraphQlItems()),
      $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems()),
    ];
    $data = $this->fabricClient->executeMultiple($queries);
    if (empty($data['plans'])) {
      return [];
    }

    $plans = [];
    foreach ($data['plans'] as $item) {
      // Add the reporting periods.
      $item->planReportingPeriods = array_map(fn ($period) => new PlanReportingPeriod($period), array_filter($data['planReportingPeriods'] ?? [], fn ($period) => $period->PlanId == $item->Id));
      $this->objectStore->addObjectCollection($item->planReportingPeriods, PlanReportingPeriod::getObjectStorageKey(), 'PlanId');

      $plans[$item->Id] = new Plan($item);
    }
    $this->objectStore->addObjects($plans);
    $retrieved = TRUE;
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
  public function getPlanReportingPeriods(int $plan_id): array {
    $reporting_periods = $this->objectStore->getObjectCollection(PlanReportingPeriod::getObjectStorageKey(), 'PlanId', $plan_id);
    if ($reporting_periods) {
      return $reporting_periods;
    }

    $items = $this->fabricClient->createQuery('planReportingPeriods', PlanReportingPeriod::getGraphQlItems())
      ->setFilter('PlanId', $plan_id)
      ->execute();

    $reporting_periods = array_map(fn ($item): PlanReportingPeriod => new PlanReportingPeriod($item), $items);
    $this->objectStore->addObjectCollection($reporting_periods, PlanReportingPeriod::getObjectStorageKey(), 'PlanId');
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
  public function getPlanTypeByName(string $name): ?PlanType {
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
  public function getPlanCostingTypeByName(string $name): ?PlanCostingType {
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
    $this->objectStore->addObject($country);
    return $country;
  }

  /**
   * Get financial data for multiple plans.
   *
   * @param int[] $plan_ids
   *   The plan IDs to fetch.
   *
   * @return array<int, array<string, float|null>>
   *   Financial data keyed by plan ID.
   */
  public function getFinancialDataForPlans(array $plan_ids): array {
    $financial_data_cache = &$this->getFinancialDataCache();
    $plan_ids = array_values(array_filter(array_unique(array_map('intval', $plan_ids))));
    $missing_plan_ids = array_values(array_diff($plan_ids, array_keys($financial_data_cache)));

    if ($missing_plan_ids) {
      $funding_by_plan = $this->getPlanFundingSummaryQuery()->getDataForPlans($missing_plan_ids);
      $attachments_by_plan = $this->getAttachmentQuery()->getAttachmentsByPlan($missing_plan_ids, 'cost');

      foreach ($missing_plan_ids as $plan_id) {
        $attachments = $attachments_by_plan[$plan_id] ?? [];
        $attachment = count($attachments) == 1 ? reset($attachments) : NULL;
        assert($attachment instanceof CostAttachment || $attachment === NULL);

        $financial_data_cache[$plan_id] = $funding_by_plan[$plan_id] + [
          'current_requirements' => $attachment?->getRequirements() ?? NULL,
          'original_requirements' => $attachment?->getOriginalRequirements() ?? NULL,
        ];
      }
    }

    $financial_data = [];
    foreach ($plan_ids as $plan_id) {
      $financial_data[$plan_id] = $financial_data_cache[$plan_id];
    }
    return $financial_data;
  }

  /**
   * Fetch the financial data for the plan.
   *
   * @return array
   *   An array with financial data for the plan.
   */
  public function fetchFinancialData(int $plan_id): array {
    return $this->getFinancialDataForPlans([$plan_id])[$plan_id];
  }

  /**
   * Get the request-wide financial data cache shared by plugin instances.
   *
   * @return array<int, array<string, float|null>>
   *   Financial data keyed by plan ID.
   */
  private function &getFinancialDataCache(): array {
    $financial_data_caches = &drupal_static(__METHOD__, []);
    $cache_base_time = (int) ($this->configuration['cache_base_time'] ?? 0);
    $financial_data_caches[$cache_base_time] = $financial_data_caches[$cache_base_time] ?? [];
    return $financial_data_caches[$cache_base_time];
  }

}
