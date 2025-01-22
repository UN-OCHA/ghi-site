<?php

namespace Drupal\ghi_menu\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\menu_item_extras\Entity\MenuItemExtrasMenuLinkContent;

/**
 * Override of MenuLinkContent.
 *
 * This is only used to set GhiMenuLinkContent as the class responsible for
 * menu item rendering. We need that to have the last word about how the
 * rendered url should be build.
 */
class GhiMenuLinkContent extends MenuItemExtrasMenuLinkContent {

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    $definition['class'] = 'Drupal\ghi_menu\Plugin\Menu\GhiMenuLinkContent';
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $tags = parent::getCacheTagsToInvalidate();
    // Add cache tags for the menu, so that changes are immediately visible.
    $menu = $this->entityTypeManager()->getStorage('menu')->load($this->getMenuName());
    $tags = Cache::mergeTags($tags, $menu?->getCacheTags() ?? []);
    return $tags;
  }

}
