<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;

/**
 * Interface for section menu item plugins.
 */
interface SectionMenuPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

  /**
   * Get the plugin configuration for an instance.
   *
   * @return array
   *   Plugin configuration array.
   */
  public function getPluginConfiguration();

  /**
   * Get the label for the plugin.
   */
  public function getPluginLabel();

  /**
   * Get the plugin description if available.
   */
  public function getPluginDescription();

  /**
   * Set the section node for the item.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section for the item.
   */
  public function setSection(SectionNodeInterface $section);

  /**
   * Get the section node for the item.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The section node or NULL if not set yet.
   */
  public function getSection();

  /**
   * Get the label of an item.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the rendered value.
   */
  public function getLabel();

  /**
   * Get the menu item object.
   *
   * @return \Drupal\ghi_sections\Menu\SectionMenuItemInterface
   *   The menu item object.
   */
  public function getItem();

  /**
   * Get the menu item object.
   *
   * @return \Drupal\ghi_sections\MenuItemType\SectionMenuWidgetBase
   *   The widget for rendering.
   */
  public function getWidget();

  /**
   * Get the status of this menu item.
   *
   * @return bool
   *   TRUE if the item is accessible, FALSE otherwise.
   */
  public function getStatus();

  /**
   * Get the cache tags for this item.
   */
  public function getCacheTags();

}
