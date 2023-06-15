<?php

namespace Drupal\hpc_api\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager class for configuration container item plugins.
 */
class EndpointQueryManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/EndpointQuery', $namespaces, $module_handler, 'Drupal\hpc_api\Query\EndpointQueryPluginInterface', 'Drupal\hpc_api\Annotation\EndpointQuery');
    $this->alterInfo('hpc_api_endpoint_query_info');
    $this->setCacheBackend($cache_backend, 'hpc_api');
  }

  /**
   * {@inheritdoc}
   */
  public function createInstance($plugin_id, array $configuration = []) {
    /** @var \Drupal\hpc_api\Query\EndpointQueryBase $instance */
    $instance = parent::createInstance($plugin_id, $configuration);
    return $instance;
  }

}
