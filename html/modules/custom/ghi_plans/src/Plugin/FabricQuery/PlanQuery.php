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
    $plans = $this->objectStore->getObjects($plan_ids, Plan::getObjectStorageKey());
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
      $this->objectStore->addObjectCollection($item->planReportingPeriods, PlanReportingPeriod::getObjectStorageKey(), 'PlanId');

      $plans[$item->Id] = new Plan($item);
    }
    $this->objectStore->addObjects($plans);
    return $plans;
  }

  /**
   * Get plans by year.
   *
   * @param int $year
   *   The year for which to fetch plans.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Plan[]
   *   An array of plan objects.
   */
  public function getPlansByYear(int $year): array {
    $plans = $this->objectStore->getObjectCollection(Plan::getObjectStorageKey(), 'year', $year);
    if (!empty($plans)) {
      return $plans;
    }
    $items = $this->fabricClient->createQuery('plans', ['Id'])
      ->setFilters([
        'planPeriod' => [
          'period' => [
            'PeriodType' => 'Year',
            'CalendarYear' => $year,
          ],
        ],
      ])
      ->execute();
    $plan_ids = $this->extractIdsFromRawData($items);
    $plans = $this->getPlansById($plan_ids);
    $this->objectStore->addObjectCollection($plans, Plan::getObjectStorageKey(), 'year');
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
   * Fetch the financial data for the plan.
   *
   * @return array
   *   An array with financial data for the plan.
   */
  public function fetchFinancialData(int $plan_id): array {
    $funding_query = $this->getPlanFundingSummaryQuery();
    $funding_query->setPlaceholder('plan_id', $plan_id);

    $attachments_query = $this->getAttachmentQuery();
    $attachments = $attachments_query->getAttachmentsByObject('plan', [$plan_id], 'cost');
    $attachment = count($attachments) == 1 ? reset($attachments) : NULL;
    assert($attachment instanceof CostAttachment || $attachment === NULL);

    return [
      'total_funding' => $funding_query->getTotalFunding(),
      'overall_funding' => $funding_query->getOverallFunding(),
      'current_requirements' => $attachment?->getRequirements() ?? NULL,
      'original_requirements' => $attachment?->getOriginalRequirements() ?? NULL,
    ];
  }

}
