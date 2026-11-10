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
 * Each plan is read only from its selected source. A missing metric remains
 * missing instead of falling back to the other source.
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
    $plans_by_source = [];
    foreach ($plans as $plan_id => $plan) {
      $source = $source_override ?? $plan->getPlanDataSource();
      $plans_by_source[$source][$plan_id] = $plan;
    }
    return $this->resolvePlanData($plans_by_source[Plan::DATA_SOURCE_PLAN] ?? []) + $this->resolveGhoData($plans_by_source[Plan::DATA_SOURCE_GHO] ?? []);
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
      $funding = $plan->getTotalFunding();
      $overall_funding = $plan->getOverallFunding();
      // The plan tag also covers an empty result so adding the first remote
      // caseload invalidates consumers that previously rendered no data.
      $cache_tags = ['plan_id:' . $plan_id];
      if ($caseload) {
        $cache_tags = Cache::mergeTags($cache_tags, $caseload->getValueCacheTags());
      }
      $resolved[$plan_id] = new PlanMetricValues(Plan::DATA_SOURCE_PLAN, array_filter([
        'total_population' => $caseload?->getCaseloadValue('total_population', 'Population'),
        'in_need' => $caseload?->getCaseloadValue('in_need'),
        'target' => $caseload?->getCaseloadValue('target'),
        'expected_reach' => $caseload?->getCaseloadValue('expected_reach', 'Expected Reach'),
        'latest_reach' => $caseload?->getCaseloadValue('latest_reach') ?? $caseload?->getCaseloadValue('cumulative_reach'),
        'requirements' => $requirements,
        'original_requirements' => $plan->getOriginalRequirements(),
        'funding' => $funding,
        'outside_funding' => $overall_funding !== NULL && $funding !== NULL ? $overall_funding - $funding : NULL,
      ], fn ($value) => $value !== NULL), $caseloads, $cache_tags);
    }
    return $resolved;
  }

  /**
   * Resolves currently valid plan-level GHO facts.
   */
  private function resolveGhoData(array $plans): array {
    if (empty($plans)) {
      return [];
    }
    $facts_by_plan = $this->ghoFactQuery->getFactsForPlans(array_keys($plans));
    $resolved = [];
    foreach (array_keys($plans) as $plan_id) {
      // A plan-level value can retain period and location references, but it
      // must belong to the GHO revision and must not have a sector dimension.
      $facts = array_filter($facts_by_plan[$plan_id] ?? [], fn (GhoFact $fact): bool => $fact->isGhoRevision() && $fact->getSectorId() === NULL && $fact->getValue() !== NULL);
      $resolved[$plan_id] = new PlanMetricValues(Plan::DATA_SOURCE_GHO, $this->selectCurrentFactValues($facts), cacheTags: ['plan_id:' . $plan_id]);
    }
    return $resolved;
  }

  /**
   * Selects one unambiguous current value for each metric.
   *
   * The data contract may provide several historical facts for a metric. Only
   * values from the latest as-of date are considered. If that still leaves
   * conflicting values, the metric is omitted rather than chosen silently.
   */
  private function selectCurrentFactValues(array $facts): array {
    $facts_by_metric = [];
    foreach ($facts as $fact) {
      $metric = self::normalizeMetricName($fact->getMetricName());
      if ($metric !== NULL) {
        $facts_by_metric[$metric][] = $fact;
      }
    }

    $values = [];
    foreach ($facts_by_metric as $metric => $metric_facts) {
      usort($metric_facts, fn (GhoFact $a, GhoFact $b): int => strcmp($b->getAsOfDate() ?? '', $a->getAsOfDate() ?? ''));
      $latest_date = reset($metric_facts)->getAsOfDate();
      $latest_facts = array_filter($metric_facts, fn (GhoFact $fact): bool => $fact->getAsOfDate() === $latest_date);
      $latest_values = array_values(array_unique(array_map(fn (GhoFact $fact): float => $fact->getValue(), $latest_facts)));
      if (count($latest_values) === 1) {
        $values[$metric] = reset($latest_values);
      }
    }
    return $values;
  }

  /**
   * Maps known Fabric aliases to stable application metric keys.
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
      'prioritized_target' => 'prioritized_target',
      'prioritized_requirements' => 'prioritized_requirements',
      default => $metric,
    };
  }

}
