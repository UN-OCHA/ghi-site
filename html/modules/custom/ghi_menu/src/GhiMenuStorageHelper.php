<?php

namespace Drupal\ghi_menu;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Menu\MenuTreeStorageInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Helper service for menu storage.
 */
class GhiMenuStorageHelper {

  /**
   * The menu tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The menu link tree storage.
   *
   * @var \Drupal\Core\Menu\MenuTreeStorageInterface
   */
  protected $treeStorage;

  /**
   * Constructs a \Drupal\Core\Menu\MenuLinkManager object.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuTreeStorageInterface $tree_storage
   *   The menu link tree storage.
   */
  public function __construct(MenuLinkTreeInterface $menu_tree, MenuTreeStorageInterface $tree_storage) {
    $this->menuTree = $menu_tree;
    $this->treeStorage = $tree_storage;
  }

  /**
   * Cleanup the menu storage.
   *
   * While it seems like an edge case, we had situations where an item in the
   * menu storage referenced a menu content link that was already deleted,
   * resulting in a WSOD due to an unhandled PluginException. This logic here
   * removes those broken items in the menu tree storage.
   */
  public function cleanupMenuStorage() {
    $parameters = new MenuTreeParameters();
    $menu_names = $this->treeStorage->getMenuNames();
    foreach ($menu_names as $menu_name) {
      $menu_tree = $this->menuTree->load($menu_name, $parameters);
      $this->purgeBrokenItems($menu_tree);
    }
  }

  /**
   * Purge broken menu links from the tree.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_tree
   *   The menu tree to filter.
   */
  private function purgeBrokenItems(array $menu_tree) {
    foreach ($menu_tree as $id => $item) {
      if (!empty($item->subtree)) {
        $this->purgeBrokenItems($item->subtree);
      }
      $link = $item->link;
      if (!$link instanceof MenuLinkContent) {
        continue;
      }
      try {
        // Just try to get the entity. If this throws an exception, this menu
        // item is broken.
        $link->getEntity();
      }
      catch (PluginException $e) {
        $this->treeStorage->delete($id);
      }

    }
  }

}
