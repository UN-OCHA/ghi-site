<?php

namespace Drupal\ghi_configuration_container;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Plugin manager class for configuration container item plugins.
 */
class ConfigurationContainerItemManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ConfigurationContainerItem', $namespaces, $module_handler, 'Drupal\ghi_configuration_container\ConfigurationContainerItemPluginInterface', 'Drupal\ghi_configuration_container\Annotation\ConfigurationContainerItem');
    $this->alterInfo('ghi_configuration_container_info');
    $this->setCacheBackend($cache_backend, 'ghi_configuration_container');
  }

}
