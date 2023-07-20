<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Plugin manager class for section menu item plugins.
 */
class SectionMenuPluginManager extends DefaultPluginManager {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SectionMenuItem', $namespaces, $module_handler, 'Drupal\ghi_sections\Menu\SectionMenuPluginInterface', 'Drupal\ghi_sections\Annotation\SectionMenuPlugin');
    $this->alterInfo('ghi_section_menu_items_info');
    $this->setCacheBackend($cache_backend, 'ghi_section_menu_items');
  }

  /**
   * Get a list of optional section menu item plugins.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section node for which the plugins should be searched.
   *
   * @return \Drupal\ghi_sections\Menu\OptionalSectionMenuPluginInterface[]
   *   An array of plugins.
   */
  public function getOptionalPluginsForSection(SectionNodeInterface $section) {
    $optional_plugins = [];
    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $plugin = $this->createInstance($plugin_id, [
        'section' => $section->id(),
      ]);
      if ($plugin instanceof OptionalSectionMenuPluginInterface && $plugin->isAvailable()) {
        $optional_plugins[$plugin_id] = $plugin;
      }
    }
    return $optional_plugins;
  }

}
