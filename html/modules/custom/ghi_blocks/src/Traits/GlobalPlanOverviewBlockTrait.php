<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Trait for global plan overview blocks.
 */
trait GlobalPlanOverviewBlockTrait {

  /**
   * Get the plan query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery
   *   The plan query plugin.
   */
  private function getPlanQuery() {
    $year = $this->getContextValue('year');
    /** @var \Drupal\hpc_api\Query\EndpointQueryPluginInterface $query_handler */
    $query_handler = $this->getQueryHandler('plans');
    $query_handler->setPlaceholder('year', $year);
    return $query_handler;
  }

}
