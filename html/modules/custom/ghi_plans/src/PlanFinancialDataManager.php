<?php

namespace Drupal\ghi_plans;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery;
use Drupal\hpc_api\Query\FabricQueryManager;

/**
 * Retrieves and updates stored financial data for plans.
 */
class PlanFinancialDataManager {

  /**
   * State key for the last plan processed during cron.
   */
  private const CRON_CURSOR_STATE_KEY = 'ghi_plans.financial_data_cron_cursor';

  /**
   * Constructs the plan financial data manager.
   */
  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager, private readonly StateInterface $state, private readonly FabricQueryManager $fabricQueryManager) {}

  /**
   * Get financial data for multiple plans.
   *
   * @param int[] $plan_ids
   *   The source plan IDs.
   * @param array $query_configuration
   *   Optional plan query configuration.
   *
   * @return array<int, array<string, float|null>>
   *   Financial data keyed by source plan ID.
   */
  public function getFinancialData(array $plan_ids, array $query_configuration = []): array {
    $plan_query = $this->fabricQueryManager->createInstance('plan', $query_configuration);
    if (!$plan_query instanceof PlanQuery) {
      throw new \UnexpectedValueException('The plan Fabric query plugin is unavailable.');
    }
    return $plan_query->getFinancialDataForPlans($plan_ids);
  }

  /**
   * Refresh and save one plan when its financial values changed.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan to update.
   *
   * @return bool
   *   TRUE when the plan was saved, FALSE otherwise.
   */
  public function updatePlan(Plan $plan): bool {
    $plan_id = $plan->getSourceId();
    if (!$plan_id) {
      return FALSE;
    }
    $financial_data = $this->getFinancialData([$plan_id])[$plan_id];
    if (!$plan->applyFinancialData($financial_data)) {
      return FALSE;
    }
    $plan->setSyncing(TRUE);
    $plan->save();
    return TRUE;
  }

  /**
   * Refresh and save a collection of plans when their values changed.
   *
   * @param \Drupal\ghi_plans\Entity\Plan[] $plans
   *   The plans to update.
   *
   * @return int
   *   The number of plans saved.
   */
  public function updatePlans(array $plans): int {
    $plans_by_source_id = [];
    foreach ($plans as $plan) {
      if ($plan instanceof Plan && $plan->getSourceId()) {
        $plans_by_source_id[$plan->getSourceId()] = $plan;
      }
    }
    if (!$plans_by_source_id) {
      return 0;
    }

    $financial_data = $this->getFinancialData(array_keys($plans_by_source_id));
    $updated = 0;
    foreach ($plans_by_source_id as $plan_id => $plan) {
      if (!$plan->applyFinancialData($financial_data[$plan_id])) {
        continue;
      }
      $plan->setSyncing(TRUE);
      $plan->save();
      $updated++;
    }
    return $updated;
  }

  /**
   * Update the next set of plans selected by the persistent cron cursor.
   *
   * @param int $limit
   *   The maximum number of plans to update.
   *
   * @return int
   *   The number of plans saved.
   */
  public function updateNextBatch(int $limit = 100): int {
    $storage = $this->entityTypeManager->getStorage('base_object');
    $cursor = (int) $this->state->get(self::CRON_CURSOR_STATE_KEY, 0);
    $results = $this->getPlanIdsAfterCursor($cursor, $limit);

    // Start the next cycle immediately after reaching the final plan.
    if (!$results && $cursor) {
      $this->state->delete(self::CRON_CURSOR_STATE_KEY);
      $results = $this->getPlanIdsAfterCursor(0, $limit);
    }
    if (!$results) {
      return 0;
    }

    $updated = $this->updatePlans($storage->loadMultiple($results));
    $this->state->set(self::CRON_CURSOR_STATE_KEY, (int) end($results));
    return $updated;
  }

  /**
   * Get plan entity IDs after the cursor.
   *
   * @param int $cursor
   *   The last processed plan entity ID.
   * @param int $limit
   *   The maximum number of plan IDs to return.
   *
   * @return int[]
   *   Plan entity IDs.
   */
  private function getPlanIdsAfterCursor(int $cursor, int $limit): array {
    $query = $this->entityTypeManager->getStorage('base_object')->getQuery();
    $query->accessCheck(FALSE);
    $query->condition('type', Plan::BUNDLE);
    if ($cursor) {
      $query->condition('id', $cursor, '>');
    }
    $query->sort('id');
    $query->range(0, $limit);
    return $query->execute();
  }

}
