<?php

namespace Drupal\hpc_api\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager class for fabric query plugins.
 */
class FabricQueryManager extends DefaultPluginManager {

  /**
   * List of source endpoint definitions.
   *
   * @var \Drupal\hpc_api\Query\FabricQuery
   */
  protected $fabricQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, FabricQuery $fabric_query) {
    parent::__construct('Plugin/FabricQuery', $namespaces, $module_handler, 'Drupal\hpc_api\Query\FabricQueryPluginInterface', 'Drupal\hpc_api\Attribute\FabricQuery');
    $this->alterInfo('hpc_api_fabric_query_info');
    $this->setCacheBackend($cache_backend, 'hpc_api_fabric_query_plugins');

    $this->fabricQuery = $fabric_query;
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []): FabricQueryBase {
    /** @var \Drupal\hpc_api\Query\FabricQueryBase $instance */
    $instance = parent::createInstance($plugin_id, $configuration);
    return $instance;
  }

  /**
   * Get the endpoint URL from the fabric query service.
   *
   * @return string
   *   The endpoint URL.
   */
  public function getEndpointUrl() {
    return $this->fabricQuery->getEndpointUrl();
  }

}
