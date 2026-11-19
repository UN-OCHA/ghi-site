<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'plan overview' fabric query.
 */
#[FabricQuery(
  id: 'plan_overview',
  label: new TranslatableMarkup('Plan overview query'),
)]
class PlanOverviewQuery extends FabricQueryBase {

  /**
   * The fetched and processed plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   */
  private $plans = NULL;

  /**
   * Plans with metric values prepared for aggregation across plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]|null
   */
  private $aggregatePlans = NULL;

  /**
   * The year to use for the overview data.
   *
   * @var int|null
   */
  private $year = NULL;

  /**
   * The plan query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery
   */
  private $planQuery = NULL;

  /**
   * The plan metric resolver.
   */
  private ?PlanMetricResolver $planMetricResolver = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PlanOverviewQuery {
    /** @var self */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance('plan');
    $instance->planMetricResolver = $container->get('ghi_plans.plan_metric_resolver');
    return $instance;
  }

  /**
   * Public setter for the year.
   *
   * @param int $year
   *   The year.
   */
  public function setYear(int $year): void {
    $this->year = $year;
  }

  /**
   * Public getter for the year.
   *
   * @return int|null
   *   The year.
   */
  public function getYear(): ?int {
    return $this->year;
  }

  /**
   * Retrieves source-aware plan overview data.
   */
  private function retrievePlans(): void {
    // Several homepage blocks request the same overview during one request, so
    // retain the processed response on this query instance.
    if ($this->plans !== NULL) {
      return;
    }
    $this->plans = [];
    $this->aggregatePlans = [];
    $year = $this->getYear();
    if ($year === NULL) {
      return;
    }

    // Get the remote plans from Fabric.
    $plans = $this->planQuery->getPlansByYear($year);
    if (empty($plans)) {
      return;
    }
    // Get the associated local base objects.
    /** @var \Drupal\ghi_plans\Entity\Plan[] $plan_objects */
    $plan_objects = BaseObjectHelper::getBaseObjectsFromOriginalIds($this->extractIds($plans), 'plan');
    foreach ($plan_objects as $plan_object) {
      $this->setCacheTags($plan_object->getCacheTags());
    }

    // Fabric can return plans that have not been imported into Drupal yet.
    // Only load overview data for plans with a matching base object.
    $plans = array_filter($plans, fn ($plan) => isset($plan_objects[$plan->id()]));
    if (empty($plans)) {
      return;
    }

    // Resolve Full values for displayed plan rows and map markers.
    $metrics_by_plan = $this->planMetricResolver->resolve($plan_objects);

    // Country Module values do not have a separate aggregation dimension.
    // Re-resolve plans whose general or prioritized source uses GHO so every
    // GHO contribution follows the Adjusted aggregation rules.
    $aggregate_metrics_by_plan = $metrics_by_plan;
    $gho_plan_objects = array_filter($plan_objects, fn (Plan $plan): bool => $plan->getPlanDataSource() === Plan::DATA_SOURCE_GHO || $plan->getEffectivePrioritizedDataSource() === Plan::DATA_SOURCE_GHO);
    if ($gho_plan_objects) {
      $aggregate_metrics_by_plan = array_replace($aggregate_metrics_by_plan, $this->planMetricResolver->resolveForAggregation($gho_plan_objects));
    }

    // Preserve remote dependencies hidden behind both normalized value sets.
    foreach (array_merge($metrics_by_plan, $aggregate_metrics_by_plan) as $metrics) {
      $this->setCacheTags($metrics->getCacheTags());
    }

    foreach ($plans as $plan) {
      $plan_id = $plan->id();
      $this->plans[$plan_id] = new PlanOverviewPlan((object) [
        'plan' => $plan,
        'metrics' => $metrics_by_plan[$plan_id],
      ]);
      $this->aggregatePlans[$plan_id] = new PlanOverviewPlan((object) [
        'plan' => $plan,
        'metrics' => $aggregate_metrics_by_plan[$plan_id],
      ]);
    }
  }

  /**
   * Get plans.
   *
   * @param bool $filter
   *   Whether the plans should be filtered or not.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   An array of plan overview plan objects keyed by plan id.
   */
  public function getPlans(bool $filter = TRUE): array {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    $plans = $this->plans;
    if ($filter) {
      // Filter by visibility settings.
      $this->filterPlansByVisibilityOnGlobalPages($plans);
    }

    uasort($plans, function (PlanOverviewPlan $a, PlanOverviewPlan $b) {
      return strnatcmp($a->getName(), $b->getName());
    });
    return $plans;
  }

  /**
   * Gets plans included in the GHO, independently of their metric source.
   *
   * @param bool $filter
   *   Whether the plans should be filtered or not.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   An array of GHO plans.
   */
  public function getGhoPlans(bool $filter = TRUE): array {
    $plans = $this->getPlans($filter);
    if (empty($plans)) {
      return [];
    }
    $plans = array_filter($plans, function (PlanOverviewPlan $plan) {
      return $plan->isPartOfGho();
    });
    return $plans;
  }

  /**
   * Gets total requirements for globally visible, GHO-included plans.
   *
   * @return float
   *   The sum of requirements for globally visible, GHO-included plans.
   */
  public function getTotalRequirements(): float {
    return $this->getGhoPlanValueTotal(fn (PlanOverviewPlan $plan) => $plan->getRequirements());
  }

  /**
   * Gets total funding for globally visible, GHO-included plans.
   *
   * @return float
   *   The sum of funding for globally visible, GHO-included plans.
   */
  public function getTotalFunding(): float {
    return $this->getGhoPlanValueTotal(fn (PlanOverviewPlan $plan) => $plan->getFunding());
  }

  /**
   * Gets total prioritized requirements for visible, GHO-included plans.
   *
   * @return float|null
   *   The sum of prioritized requirements, or NULL when no plan supplies one.
   */
  public function getTotalPrioritizedRequirements(): ?float {
    return $this->getOptionalGhoPlanValueTotal(fn (PlanOverviewPlan $plan) => $plan->getPrioritizedRequirements());
  }

  /**
   * Sums a plan value across globally visible, GHO-included plans.
   *
   * Missing values do not prevent other plans from contributing to the total.
   *
   * @param callable $value_callback
   *   The callback that returns the value for one plan.
   *
   * @return float
   *   The metric total.
   */
  private function getGhoPlanValueTotal(callable $value_callback): float {
    $total = 0;
    foreach ($this->getAggregateGhoPlans() as $plan) {
      $total += $value_callback($plan) ?? 0;
    }
    return $total;
  }

  /**
   * Sums an optional plan value while distinguishing missing data from zero.
   */
  private function getOptionalGhoPlanValueTotal(callable $value_callback): ?float {
    $total = 0;
    $has_value = FALSE;
    foreach ($this->getAggregateGhoPlans() as $plan) {
      $value = $value_callback($plan);
      if ($value === NULL) {
        continue;
      }
      $total += $value;
      $has_value = TRUE;
    }
    return $has_value ? $total : NULL;
  }

  /**
   * Gets aggregate-ready values for the visible plans included in the GHO.
   *
   * Plan rows and map markers continue using getGhoPlans(), which exposes Full
   * GHO values. This parallel set is reserved for cross-plan totals.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   Aggregate-ready plans keyed by plan ID.
   */
  private function getAggregateGhoPlans(): array {
    $gho_plans = $this->getGhoPlans();
    return array_intersect_key($this->aggregatePlans ?? [], $gho_plans);
  }

  /**
   * Gets the affected countries of globally visible, GHO-included plans.
   *
   * @return int
   *   The number of unique countries of globally visible, GHO-included plans.
   */
  public function getNumberOfGhoCountries(): int {
    // Use the same visibility-filtered plan set as the global plan table so
    // the headline figures stay in sync with the displayed rows.
    $plans = $this->getGhoPlans();
    if (empty($plans)) {
      return 0;
    }

    $countries = [];
    foreach ($plans as $plan) {
      $plan_countries = $plan->getCountries();
      if (empty($plan_countries)) {
        continue;
      }
      foreach ($plan_countries as $plan_country) {
        if (array_key_exists($plan_country->id(), $countries)) {
          continue;
        }
        $countries[$plan_country->id()] = $plan_country;
      }
    }

    return count($countries);
  }

  /**
   * Gets population metric totals for the supplied types.
   *
   * @param array $types
   *   The population metric types to sum. Keys are normalized metric names and
   *   values are attachment labels used only by Country Module data.
   *
   * @return array
   *   An array keyed by metric type and valued by its total sum.
   */
  public function getCaseloadTotalValues(array $types): array {
    // Use the same visibility-filtered plan set as the global plan table so
    // headline totals are the sum of the displayed plan rows.
    $plans = $this->getAggregateGhoPlans();

    // Initialize all totals so missing values on individual plans do not
    // prevent available values from contributing to the aggregate.
    $caseload_totals = array_fill_keys(array_keys($types), 0);
    $caseload_value_available = array_fill_keys(array_keys($types), FALSE);
    $plan_caseloads = [];
    $caseload_totals['target_custom'] = 0;
    $caseload_totals['reached_custom'] = 0;

    foreach ($plans as $plan) {
      $plan_caseloads[$plan->id()] = [];

      foreach ($types as $type => $label) {
        $value = $plan->getCaseloadValue($type, $label);
        $caseload_totals[$type] += $value ?? 0;
        $caseload_value_available[$type] = $caseload_value_available[$type] || $value !== NULL;
        $plan_caseloads[$plan->id()][$type] = $value;
      }
    }

    // Prioritized metrics are a recent addition and are not available for all
    // years. Preserve an actual zero, but do not present complete absence as
    // a misleading numeric value.
    foreach (Plan::PRIORITIZED_METRICS as $type) {
      if (array_key_exists($type, $caseload_totals) && !$caseload_value_available[$type]) {
        $caseload_totals[$type] = NULL;
      }
    }

    // Calculate custom target and reached values, to be used for percentage
    // calculations in KeyFigures::getData(), based on additional business
    // logic.
    foreach ($plan_caseloads as $plan_id => $caseload) {
      $plan = $plans[$plan_id];
      if ($plan->isRrp()) {
        continue;
      }
      if (empty($caseload['target']) || empty($caseload['latest_reach'])) {
        // The progress denominator includes only plans with non-zero target and
        // reach values; a zero target cannot produce meaningful coverage.
        continue;
      }
      $caseload_totals['target_custom'] += $caseload['target'];
      // If reached is higher than target, use target instead, so that the
      // final percentage per plan can't get over 100%.
      $caseload_totals['reached_custom'] += min($caseload['latest_reach'], $caseload['target']);
    }

    return $caseload_totals;
  }

  /**
   * Filter the given list of plans by global visibility settings.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   An array of plan objects.
   */
  private function filterPlansByVisibilityOnGlobalPages(array &$plans): void {
    if (empty($plans)) {
      return;
    }
    $result = \Drupal::entityTypeManager()
      ->getStorage('base_object')
      ->loadByProperties([
        'type' => 'plan',
        'field_original_id' => array_keys($plans),
        'field_visible_on_global_pages' => 1,
      ]);
    if (empty($result)) {
      $plans = [];
      return;
    }
    $plan_ids = array_map(function ($plan_entity) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan_entity */
      return $plan_entity->getSourceId();
    }, $result);
    $plans = array_filter($plans, function ($plan) use ($plan_ids) {
      return in_array($plan->id(), $plan_ids);
    });
  }

}
