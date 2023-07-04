<?php

namespace Drupal\ghi_sections\Menu;

/**
 * Interface for section menu items.
 */
interface SectionMenuItemInterface {

  /**
   * Get the plugin that provides the menu item.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuPluginInterface
   *   The plugin instance.
   */
  public function getPlugin();

  /**
   * Get the menu item configuration.
   *
   * @return array
   *   An associative array with the item configuration.
   */
  public function getConfiguration();

  /**
   * Get the label of the menu item.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the rendered value.
   */
  public function getLabel();

  /**
   * Returns an array representation of the menu item.
   *
   * @return array
   *   An array representation of the section menu item.
   */
  public function toArray();

  /**
   * Creates an object from an array representation of the menu item.
   *
   * @param array $data
   *   An array of data in the format returned by ::toArray().
   *
   * @return static
   *   The menu item object.
   */
  public static function fromArray($data);

}
