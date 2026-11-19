<?php

namespace Drupal\ghi_plans;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Facts\GhoFact;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery;
use Drupal\ghi_plans\Plugin\FabricQuery\GhoFactQuery;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_api\Query\FabricQueryManager;

/**
 * Resolves plan-level metrics without exposing source-specific storage.
 *
 * Source-selectable metrics are read only from the configured source. Funding
 * always comes from the FTS-backed fields on the imported plan.
 */
class PlanMetricResolver {

  /**
   * The attachment query.
   */
  private AttachmentQuery $attachmentQuery;

  /**
   * The attachment prototype query.
   */
  private AttachmentPrototypeQuery $attachmentPrototypeQuery;

  /**
   * The GHO fact query.
   */
  private GhoFactQuery $ghoFactQuery;

  /**
   * Constructs the plan metric resolver.
   */
  public function __construct(FabricQueryManager $fabric_query_manager) {
    $this->attachmentQuery = $fabric_query_manager->createInstance('attachment');
    $this->attachmentPrototypeQuery = $fabric_query_manager->createInstance('attachment_prototype');
    $this->ghoFactQuery = $fabric_query_manager->createInstance('gho_fact');
  }

  /**
   * Resolves metrics for imported plans using each plan's configured source.
   *
   * @param \Drupal\ghi_plans\Entity\Plan[] $plans
   *   Plan entities keyed by Fabric plan ID.
   * @param string|null $source_override
   *   An optional component-level source pin. NULL uses each plan's setting.
   *
   * @return \Drupal\ghi_plans\ValueObject\PlanMetricValues[]
   *   Metric sets keyed by Fabric plan ID.
   */
  public function resolve(array $plans, ?string $source_override = NULL): array {
    return $this->resolveBySource($plans, $source_override, FALSE);
  }

  /**
   * Resolves metrics for a total that aggregates multiple plans.
   *
   * Country Module values are unchanged. GHO values use their Adjusted
   * aggregation when available so overlapping plans are not counted twice,
   * and fall back to Full when Fabric provides no Adjusted value.
   *
   * @param \Drupal\ghi_plans\Entity\Plan[] $plans
   *   Plan entities keyed by Fabric plan ID.
   * @param string|null $source_override
   *   An optional component-level source pin. NULL uses each plan's setting.
   *
   * @return \Drupal\ghi_plans\ValueObject\PlanMetricValues[]
   *   Metric sets keyed by Fabric plan ID.
   */
  public function resolveForAggregation(array $plans, ?string $source_override = NULL): array {
    return $this->resolveBySource($plans, $source_override, TRUE);
  }

  /**
   * Resolves metrics from the selected source.
   */
  private function resolveBySource(array $plans, ?string $source_override, bool $for_aggregation): array {
    $plans_by_source = [];
    foreach ($plans as $plan_id => $plan) {
      $source = $source_override ?? $plan->getPlanDataSource();
      $plans_by_source[$source][$plan_id] = $plan;
    }
    return $this->resolvePlanData($plans_by_source[Plan::DATA_SOURCE_PLAN] ?? []) + $this->resolveGhoData($plans_by_source[Plan::DATA_SOURCE_GHO] ?? [], $for_aggregation);
  }

  /**
   * Resolves caseload attachments and imported plan financial metrics.
   */
  private function resolvePlanData(array $plans): array {
    if (empty($plans)) {
      return [];
    }
    $plan_ids = array_keys($plans);
    // Caseload value extraction depends on attachment prototypes. Preload them
    // once for the complete plan set before hydrating the attachments.
    $this->attachmentPrototypeQuery->getDataPrototypesForPlans($plan_ids, FALSE);
    $attachments = $this->attachmentQuery->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_PLAN, $plan_ids, 'caseload');
    $caseloads_by_plan = [];
    foreach ($attachments as $attachment) {
      if ($attachment instanceof CaseloadAttachmentInterface) {
        $caseloads_by_plan[$attachment->getPlanId()][$attachment->id()] = $attachment;
      }
    }

    $resolved = [];
    foreach ($plans as $plan_id => $plan) {
      $caseloads = $caseloads_by_plan[$plan_id] ?? [];
      $caseload = $plan->getPlanCaseload($caseloads);
      $requirements = $plan->getRequirements();
      // The plan tag also covers an empty result so adding the first remote
      // caseload invalidates consumers that previously rendered no data.
      $cache_tags = ['plan_id:' . $plan_id];
      if ($caseload) {
        $cache_tags = Cache::mergeTags($cache_tags, $caseload->getValueCacheTags());
      }
      $values = array_filter([
        'total_population' => $caseload?->getCaseloadValue('total_population', 'Population'),
        'in_need' => $caseload?->getCaseloadValue('in_need'),
        'target' => $caseload?->getCaseloadValue('target'),
        'expected_reach' => $caseload?->getCaseloadValue('expected_reach', 'Expected Reach'),
        'latest_reach' => $caseload?->getCaseloadValue('latest_reach') ?? $caseload?->getCaseloadValue('cumulative_reach'),
        'requirements' => $requirements,
        'original_requirements' => $plan->getOriginalRequirements(),
      ], fn ($value) => $value !== NULL);
      $source_values_available = !empty($values);
      $values += $this->getFtsFundingValues($plan);
      $resolved[$plan_id] = new PlanMetricValues(Plan::DATA_SOURCE_PLAN, $values, $caseloads, $cache_tags, $source_values_available);
    }
    return $resolved;
  }

  /**
   * Resolves currently valid plan-level GHO facts.
   */
  private function resolveGhoData(array $plans, bool $for_aggregation): array {
    if (empty($plans)) {
      return [];
    }
    $facts_by_plan = $this->ghoFactQuery->getFactsForPlans(array_keys($plans));
    $resolved = [];
    foreach ($plans as $plan_id => $plan) {
      // Plan-level consumers exclude sector breakdowns. Location breakdowns
      // are retained here because they form the total when Fabric does not
      // provide a location-neutral row.
      $facts = array_filter($facts_by_plan[$plan_id] ?? [], fn (GhoFact $fact): bool => $fact->isGhoRevision() && $fact->getSectorId() === NULL && $fact->getValue() !== NULL);
      $values = $this->selectCurrentFactValues($facts, $for_aggregation);
      // Funding is authoritative in FTS even if Fabric exposes a GHO fact with
      // the same metric name. Never treat that fact as a fallback.
      unset($values['funding'], $values['outside_funding']);
      $source_values_available = !empty($values);
      $values += $this->getFtsFundingValues($plan);
      $resolved[$plan_id] = new PlanMetricValues(Plan::DATA_SOURCE_GHO, $values, cacheTags: ['plan_id:' . $plan_id], sourceValuesAvailable: $source_values_available);
    }
    return $resolved;
  }

  /**
   * Gets the canonical FTS-backed funding values stored on a plan.
   */
  private function getFtsFundingValues(Plan $plan): array {
    $funding = $plan->getTotalFunding();
    $overall_funding = $plan->getOverallFunding();
    return array_filter([
      'funding' => $funding,
      'outside_funding' => $overall_funding !== NULL && $funding !== NULL ? $overall_funding - $funding : NULL,
    ], fn ($value) => $value !== NULL);
  }

  /**
   * Selects one current plan total for each metric.
   *
   * Individual plans use Full facts. Totals across plans prefer Adjusted facts
   * and fall back to Full only when Adjusted is absent. An explicit Adjusted
   * zero is therefore retained. Within that aggregation, a location-neutral
   * row is the plan total; otherwise non-overlapping location rows are summed.
   */
  private function selectCurrentFactValues(array $facts, bool $for_aggregation): array {
    $facts_by_metric = [];
    foreach ($facts as $fact) {
      $metric = self::normalizeMetricName($fact->getMetricName());
      if ($metric !== NULL) {
        $facts_by_metric[$metric][] = $fact;
      }
    }

    $values = [];
    foreach ($facts_by_metric as $metric => $metric_facts) {
      $value = $this->getLocationTotal($metric_facts, $for_aggregation);
      if ($value !== NULL) {
        $values[$metric] = $value;
      }
    }
    return $values;
  }

  /**
   * Selects the applicable Fabric aggregation for one metric and location.
   */
  private function selectAggregationFacts(array $facts, bool $for_aggregation): array {
    if ($for_aggregation) {
      $adjusted_facts = array_filter($facts, fn (GhoFact $fact): bool => $fact->isAdjustedAggregation());
      if ($adjusted_facts) {
        return $adjusted_facts;
      }
    }
    return array_filter($facts, fn (GhoFact $fact): bool => $fact->isFullAggregation());
  }

  /**
   * Gets a total from location-neutral or non-overlapping location facts.
   *
   * Full plan values prefer the location-neutral total. For higher-level
   * aggregation, an Adjusted location-neutral total is preferred; otherwise
   * each location chooses Adjusted with a Full fallback before being summed.
   */
  private function getLocationTotal(array $facts, bool $for_aggregation): ?float {
    $facts_by_location = [];
    foreach ($facts as $fact) {
      $location_key = $fact->getLocationId() ?? 'plan_total';
      $facts_by_location[$location_key][] = $fact;
    }

    $plan_total_facts = $facts_by_location['plan_total'] ?? [];
    unset($facts_by_location['plan_total']);
    if (!$for_aggregation && $plan_total_facts) {
      return $this->getCurrentFactValue($plan_total_facts, FALSE);
    }
    if ($for_aggregation && array_filter($plan_total_facts, fn (GhoFact $fact): bool => $fact->isAdjustedAggregation())) {
      return $this->getCurrentFactValue($plan_total_facts, TRUE);
    }
    if (empty($facts_by_location)) {
      return $plan_total_facts ? $this->getCurrentFactValue($plan_total_facts, $for_aggregation) : NULL;
    }

    $total = 0.0;
    foreach ($facts_by_location as $location_facts) {
      $value = $this->getCurrentFactValue($location_facts, $for_aggregation);
      if ($value === NULL) {
        return NULL;
      }
      $total += $value;
    }
    return $total;
  }

  /**
   * Gets the latest value for one metric and location.
   *
   * Conflicting values for the same location and date are ambiguous and cause
   * the metric to be omitted instead of silently double-counting the location.
   */
  private function getCurrentFactValue(array $facts, bool $for_aggregation): ?float {
    $facts = $this->selectAggregationFacts($facts, $for_aggregation);
    if (empty($facts)) {
      return NULL;
    }
    usort($facts, fn (GhoFact $a, GhoFact $b): int => strcmp($b->getAsOfDate() ?? '', $a->getAsOfDate() ?? ''));
    $latest_date = reset($facts)->getAsOfDate();
    $latest_facts = array_filter($facts, fn (GhoFact $fact): bool => $fact->getAsOfDate() === $latest_date);
    $values = array_values(array_unique(array_map(fn (GhoFact $fact): float => $fact->getValue(), $latest_facts)));
    return count($values) === 1 ? reset($values) : NULL;
  }

  /**
   * Maps Fabric machine names to stable application metric keys.
   *
   * Other normalized machine names are retained so future GHO metrics remain
   * available without extending this alias list first.
   */
  public static function normalizeMetricName(?string $metric): ?string {
    return match ($metric) {
      'population', 'total_population' => 'total_population',
      'in_need' => 'in_need',
      'target' => 'target',
      'expected_reach' => 'expected_reach',
      'reached', 'latest_reach', 'cumulative_reach' => 'latest_reach',
      'requirements' => 'requirements',
      'funding' => 'funding',
      'prioritized_people_targeted' => 'prioritized_target',
      'prioritized_total_cost' => 'prioritized_requirements',
      default => $metric,
    };
  }

}
