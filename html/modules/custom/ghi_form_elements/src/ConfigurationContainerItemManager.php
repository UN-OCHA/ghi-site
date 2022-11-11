<?php

namespace Drupal\ghi_form_elements;

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
    parent::__construct('Plugin/ConfigurationContainerItem', $namespaces, $module_handler, 'Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface', 'Drupal\ghi_form_elements\Annotation\ConfigurationContainerItem');
    $this->alterInfo('ghi_form_elements_info');
    $this->setCacheBackend($cache_backend, 'ghi_form_elements');
  }

}
