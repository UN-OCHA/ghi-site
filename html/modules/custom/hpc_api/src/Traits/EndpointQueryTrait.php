<?php

namespace Drupal\hpc_api\Traits;

use Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Trait to help with plan related endpoint queries.
 */
trait EndpointQueryTrait {

  /**
   * Get a query instance by id.
   *
   * @param string $plugin_id
   *   The plugin id of the fabric query plugin.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryBase|null
   *   The query instance or NULL.
   */
  protected static function getEndpointQueryInstance($plugin_id): ?EndpointQueryBase {
    $queries = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($plugin_id, $queries)) {
      $query_manager = self::getEndpointQueryManager();
      $query = $query_manager->hasDefinition($plugin_id) ? $query_manager->createInstance($plugin_id) : NULL;
      $queries[$plugin_id] = $query instanceof EndpointQueryBase ? $query : NULL;
    }
    return $queries[$plugin_id];
  }

  /**
   * Get the plan funding summary query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanFundingSummaryQuery|null
   *   The plan funding summary query or NULL.
   */
  protected static function getPlanFundingSummaryQuery(): ?PlanFundingSummaryQuery {
    $query = self::getEndpointQueryInstance('plan_funding_summary_query');
    return $query instanceof PlanFundingSummaryQuery ? $query : NULL;
  }

  /**
   * Get the endpoint query manager service.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  protected static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

}
