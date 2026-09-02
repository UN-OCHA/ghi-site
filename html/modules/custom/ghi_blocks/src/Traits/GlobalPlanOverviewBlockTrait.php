<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Trait for global plan overview blocks.
 */
trait GlobalPlanOverviewBlockTrait {

  /**
   * Get the plan overview query.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery
   *   The plan overview query plugin.
   */
  private function getPlanOverviewQuery() {
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery $query */
    $query = $this->getQueryHandler('plans_overview');
    return $query;
  }

}
