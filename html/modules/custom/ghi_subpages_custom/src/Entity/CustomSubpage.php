<?php

namespace Drupal\ghi_subpages_custom\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_subpages\Entity\SubpageManualInterface;
use Drupal\ghi_subpages\Entity\SubpageNode;
use Drupal\ghi_subpages_custom\Plugin\SectionMenuItem\CustomSubpage as SectionMenuItemCustomSubpage;

/**
 * Class for custom subpage nodes.
 */
class CustomSubpage extends SubpageNode implements SubpageManualInterface {

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $section = $this->getParentBaseNode();
    if ($section instanceof Section) {
      $this->createSectionMenuItem($this);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $nodes) {
    /** @var \Drupal\ghi_subpages_custom\Entity\CustomSubpage[] $nodes */
    parent::postDelete($storage, $nodes);
    foreach ($nodes as $node) {
      self::deleteSectionMenuItem($node);
    }
  }

  /**
   * Create a section menu item for the custom subpage.
   */
  private static function createSectionMenuItem(CustomSubpage $node) {
    $menu_item = $node->getSectionMenuItem();
    if ($menu_item) {
      return;
    }
    // If it doesn't exist yet, create it.
    $node->getSectionMenuStorage($node)->createMenuItem('custom_subpage', ['node_id' => $node->id()]);
  }

  /**
   * Delete the section menu item for this custom subpage.
   */
  private static function deleteSectionMenuItem(CustomSubpage $node) {
    $menu_item = $node->getSectionMenuItem();
    if (!$menu_item) {
      return;
    }
    // Delete the menu item.
    $node->getSectionMenuStorage($node)->removeMenuItem($menu_item);
  }

  /**
   * Get the section menu item representing this custom subpage node.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuItemInterface|null
   *   A section menu item or NULL if not found.
   */
  public function getSectionMenuItem() {
    $section_menu_storage = $this->getSectionMenuStorage($this);
    if (!$section_menu_storage) {
      return NULL;
    }
    $menu_items = $section_menu_storage->getSectionMenuItems();
    if (!$menu_items || $menu_items->isEmpty()) {
      return NULL;
    }
    foreach ($menu_items->getAll() as $menu_item) {
      $plugin = $menu_item->getPlugin();
      if (!$plugin || !$plugin instanceof SectionMenuItemCustomSubpage) {
        continue;
      }
      if ($plugin->getNodeId() == $this->id()) {
        return $menu_item;
      }
    }
    return NULL;
  }

  /**
   * Get the section menu storage service.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuStorage|null
   *   The section menu storage service.
   */
  public static function getSectionMenuStorage(CustomSubpage $node) {
    $section = $node->getParentBaseNode();
    if (!$section) {
      return NULL;
    }
    /** @var \Drupal\ghi_sections\Menu\SectionMenuStorage $section_menu_storage */
    $section_menu_storage = \Drupal::service('ghi_sections.section_menu.storage');
    $section_menu_storage->setSection($section);
    return $section_menu_storage;
  }

}
