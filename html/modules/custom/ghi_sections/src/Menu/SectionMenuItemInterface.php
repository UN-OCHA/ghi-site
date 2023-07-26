<?php

namespace Drupal\ghi_sections\Menu;

use Drupal\Core\Form\FormStateInterface;

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
   *   Return the label.
   */
  public function getLabel();

  /**
   * Set the label of the menu item.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   The label.
   */
  public function setLabel($label);

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

  /**
   * Build the configuration form for the menu item.
   *
   * @param array $form
   *   A form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The form array with the configuration form.
   */
  public function buildForm($form, FormStateInterface $form_state);

}
