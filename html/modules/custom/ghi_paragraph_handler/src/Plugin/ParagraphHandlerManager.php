<?php

namespace Drupal\ghi_paragraph_handler\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Paragraph handler plugin manager.
 */
class ParagraphHandlerManager extends DefaultPluginManager {

  /**
   * Constructs a new ParagraphHandlerManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/ParagraphHandler', $namespaces, $module_handler, 'Drupal\ghi_paragraph_handler\Plugin\ParagraphHandlerInterface', 'Drupal\ghi_paragraph_handler\Annotation\ParagraphHandler');

    $this->alterInfo('ghi_paragraph_handler_ghi_paragraph_handler_info');
    $this->setCacheBackend($cache_backend, 'ghi_paragraph_handler_ghi_paragraph_handler_plugins');
  }

}
