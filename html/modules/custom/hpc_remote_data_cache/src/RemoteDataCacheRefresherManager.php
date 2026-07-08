<?php

namespace Drupal\hpc_remote_data_cache;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\hpc_remote_data_cache\Attribute\RemoteDataCacheRefresher;

/**
 * Plugin manager for remote data cache refreshers.
 */
class RemoteDataCacheRefresherManager extends DefaultPluginManager {

  /**
   * Constructs a remote data cache refresher manager.
   *
   * @param \Traversable $namespaces
   *   The root paths keyed by namespace.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/RemoteDataCacheRefresher', $namespaces, $module_handler, RemoteDataCacheRefresherInterface::class, RemoteDataCacheRefresher::class);
    $this->setCacheBackend($cache_backend, 'hpc_remote_data_cache_refresher_plugins');
  }

}
