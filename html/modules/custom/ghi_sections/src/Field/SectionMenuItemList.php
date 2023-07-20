<?php

namespace Drupal\ghi_sections\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\ghi_sections\Menu\SectionMenuItemInterface;

/**
 * Defines an item list class for section menu fields.
 *
 * @see \Drupal\ghi_sections\Plugin\Field\FieldType\SectionMenuItem
 */
class SectionMenuItemList extends FieldItemList {

  /**
   * Numerically indexed array of field items.
   *
   * @var \Drupal\ghi_sections\Plugin\Field\FieldType\SectionMenuItem[]
   */
  protected $list = [];

  /**
   * Get all menu items.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuItemInterface[]
   *   An array of menu items
   */
  public function getAll() {
    $menu_items = [];
    foreach ($this->list as $delta => $item) {
      $menu_items[$delta] = $item->menu_item;
    }
    return $menu_items;
  }

  /**
   * Set the menu items or this section.
   *
   * @param \Drupal\ghi_sections\Menu\SectionMenuItemInterface[] $menu_items
   *   The menu items to set.
   */
  public function setMenuItems(array $menu_items) {
    $this->list = [];
    $menu_items = array_values($menu_items);
    /** @var \Drupal\ghi_sections\Plugin\Field\FieldType\SectionMenuItem $item */
    foreach ($menu_items as $menu_item) {
      $item = $this->appendItem();
      $item->menu_item = $menu_item;
    }
    return $this;
  }

  /**
   * Remove the given menu item from the list.
   *
   * @param \Drupal\ghi_sections\Menu\SectionMenuItemInterface $menu_item
   *   The menu item to remove.
   *
   * @return bool
   *   TRUE if the item has been removed, FALSE otherwise.
   */
  public function removeMenuItem(SectionMenuItemInterface $menu_item) {
    $delta = $this->getIndex($menu_item);
    if ($delta === NULL) {
      return FALSE;
    }
    $this->removeItem($delta);
    return TRUE;
  }

  /**
   * Get the index for the given menu item.
   *
   * @param \Drupal\ghi_sections\Menu\SectionMenuItemInterface $menu_item
   *   The menu item to get the index for.
   *
   * @return int|null
   *   The index of the menu item or NULL if not found.
   */
  public function getIndex(SectionMenuItemInterface $menu_item) {
    foreach ($this->getAll() as $delta => $item) {
      if ($item == $menu_item) {
        return $delta;
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity() {
    $entity = parent::getEntity();

    // Ensure the entity is updated with the latest value.
    $entity->set($this->getName(), $this->getValue());
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    // Loop through each section and reconstruct it to ensure that all default
    // values are present.
    foreach ($this->list as $item) {
      $item->menu_item = $item->menu_item::fromArray($item->menu_item->toArray());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function equals(FieldItemListInterface $list_to_compare) {
    if (!$list_to_compare instanceof SectionMenuItemList) {
      return FALSE;
    }

    // Convert arrays of section objects to array values for comparison.
    $convert = function (SectionMenuItemList $list) {
      return array_map(function (SectionMenuItemInterface $menu_item) {
        return $menu_item->toArray();
      }, $list->getAll());
    };
    return $convert($this) === $convert($list_to_compare);
  }

  /**
   * Reorder the items.
   *
   * @param int[] $deltas
   *   The new order base don existing deltas.
   */
  public function setNewOrder(array $deltas) {
    $menu_items = $this->getAll();
    $ordered = array_filter(array_map(function ($delta) use ($menu_items) {
      return $menu_items[$delta] ?? NULL;
    }, array_combine($deltas, $deltas)));
    $ordered += $menu_items;
    $this->setMenuItems($ordered);
  }

  /**
   * Magic method: Implements a deep clone.
   */
  public function __clone() {
    $menu_items = $this->getAll();

    foreach ($menu_items as $delta => $menu_item) {
      $menu_items[$delta] = clone $menu_item;
    }

    $this->setMenuItems($menu_items);
  }

}
