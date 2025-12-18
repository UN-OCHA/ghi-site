<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Trait for global plan overview blocks.
 */
trait GlobalPlanOverviewBlockTrait {

  /**
   * Get the plan overview query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery
   *   The plan overview query plugin.
   */
  private function getPlanOverviewQuery() {
    $year = $this->getContextValue('year');
    /** @var \Drupal\hpc_api\Query\EndpointQueryPluginInterface $query_handler */
    $query_handler = $this->getQueryHandler('plans_overview');
    $query_handler->setPlaceholder('year', $year);
    return $query_handler;
  }

}
