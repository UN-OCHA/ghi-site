<?php

namespace Drupal\hpc_api\Traits;

use Drupal\hpc_api\Plugin\FabricQuery\BaseTypeQuery;
use Drupal\hpc_api\Plugin\FabricQuery\CategoryQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Trait to help with plan related fabric queries.
 */
trait FabricQueryTrait {

  /**
   * Get a query instance by id.
   *
   * @param string $plugin_id
   *   The plugin id of the fabric query plugin.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryBase|null
   *   The query instance or NULL.
   */
  protected static function getQueryInstance($plugin_id): ?FabricQueryBase {
    $queries = &drupal_static(__FUNCTION__, []);
    if (!array_key_exists($plugin_id, $queries)) {
      $query_manager = self::getFabricQueryManager();
      $query = $query_manager->hasDefinition($plugin_id) ? $query_manager->createInstance($plugin_id) : NULL;
      $queries[$plugin_id] = $query instanceof FabricQueryBase ? $query : NULL;
    }
    return $queries[$plugin_id];
  }

  /**
   * Get the country query.
   *
   * @return \Drupal\hpc_api\Plugin\FabricQuery\BaseTypeQuery|null
   *   The category query or NULL.
   */
  protected static function getBaseTypeQuery(): ?BaseTypeQuery {
    $query = self::getQueryInstance('base_type');
    return $query instanceof BaseTypeQuery ? $query : NULL;
  }

  /**
   * Get the country query.
   *
   * @return \Drupal\hpc_api\Plugin\FabricQuery\CategoryQuery|null
   *   The category query or NULL.
   */
  protected static function getCategoryQuery(): ?CategoryQuery {
    $query = self::getQueryInstance('category');
    return $query instanceof CategoryQuery ? $query : NULL;
  }

  /**
   * Get the fabric query manager.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryManager
   *   The fabric query manager service.
   */
  protected static function getFabricQueryManager() {
    return \Drupal::service('plugin.manager.fabric_query_manager');
  }

}
