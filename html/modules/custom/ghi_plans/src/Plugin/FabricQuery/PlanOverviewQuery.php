<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
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

    // Resolve metrics according to each local plan's configured data source.
    $metrics_by_plan = $this->planMetricResolver->resolve($plan_objects);
    // Preserve remote dependencies hidden behind the normalized values so a
    // Fabric refresh invalidates every homepage consumer of this query.
    foreach ($metrics_by_plan as $metrics) {
      $this->setCacheTags($metrics->getCacheTags());
    }

    foreach ($plans as $plan) {
      $plan_id = $plan->id();
      $plan = new PlanOverviewPlan((object) [
        'plan' => $plan,
        'metrics' => $metrics_by_plan[$plan_id],
      ]);
      $this->plans[$plan->id()] = $plan;
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
    $plans = $this->getGhoPlans();
    if (empty($plans)) {
      return 0;
    }
    $requirements = 0;
    foreach ($plans as $plan) {
      $requirements += $plan->getRequirements();
    }
    return $requirements;
  }

  /**
   * Gets total funding for globally visible, GHO-included plans.
   *
   * @return float
   *   The sum of funding for globally visible, GHO-included plans.
   */
  public function getTotalFunding(): float {
    $plans = $this->getGhoPlans();
    if (empty($plans)) {
      return 0;
    }
    $funding = 0;
    foreach ($plans as $plan) {
      $funding += $plan->getFunding();
    }
    return $funding;
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
    $plans = $this->getGhoPlans();

    // Initialize all totals so missing source values contribute zero.
    $caseload_totals = array_fill_keys(array_keys($types), 0);
    $plan_caseloads = [];
    $caseload_totals['target_custom'] = 0;
    $caseload_totals['reached_custom'] = 0;

    // Load Country Module caseload overrides. GHO-backed plans ignore them and
    // expose their normalized metric set directly.
    $attachment_overrides = $this->getPlanCaseloadOverridesByPlanId();

    foreach ($plans as $plan) {
      $caseload_items = $plan->getPlanCaseloadFields($attachment_overrides[$plan->id()] ?? NULL);
      if (empty($caseload_items)) {
        continue;
      }

      $plan_caseloads[$plan->id()] = [];

      foreach ($types as $type => $label) {
        $value = $plan->getCaseloadValue($type, $label);
        $caseload_totals[$type] += $value ?? 0;
        $plan_caseloads[$plan->id()][$type] = $value;
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
   * Gets selected Country Module caseload IDs keyed by plan ID.
   *
   * A plan base object can select the Country Module caseload to use. Load all
   * selections in one query; GHO-backed plans do not consume these overrides.
   *
   * @return array
   *   Selected attachment IDs keyed by plan ID.
   */
  private function getPlanCaseloadOverridesByPlanId(): array {
    $plans = $this->getPlans();
    $caseload_overrides = [];
    if (empty($plans)) {
      return $caseload_overrides;
    }
    $result = \Drupal::entityTypeManager()
      ->getStorage('base_object')
      ->loadByProperties([
        'type' => 'plan',
        'field_original_id' => array_keys($plans),
      ]);
    if (empty($result)) {
      return $caseload_overrides;
    }
    foreach ($result as $plan) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $attachment_id = $plan->field_plan_caseload->attachment_id;
      $caseload_overrides[$plan->getSourceId()] = $attachment_id !== NULL ? (int) $attachment_id : NULL;
    }
    return array_filter($caseload_overrides);
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
