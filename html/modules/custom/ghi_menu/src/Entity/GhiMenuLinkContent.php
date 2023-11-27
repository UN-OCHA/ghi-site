<?php

namespace Drupal\ghi_menu\Entity;

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

}
