<?php

namespace Drupal\ghi_content\RemoteSource;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager class for remote source plugins.
 */
class RemoteSourceManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/RemoteSource', $namespaces, $module_handler, 'Drupal\ghi_content\RemoteSource\RemoteSourceInterface', 'Drupal\ghi_content\Annotation\RemoteSource');
    $this->alterInfo('ghi_content_info');
    $this->setCacheBackend($cache_backend, 'ghi_content.plugin.remote_source');
  }

}
