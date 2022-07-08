<?php

namespace Drupal\ghi_form_elements;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Interface for configuration container item plugins.
 */
interface ConfigurationContainerItemPluginInterface extends PluginInspectionInterface, ContainerFactoryPluginInterface {

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
   * Builds the associated form.
   *
   * @param array $element
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   Form array
   */
  public function buildForm(array $element, FormStateInterface $form_state);

  /**
   * Get the configuration for an instance.
   *
   * @return array
   *   Arbitrary config array, depending on the type of item.
   */
  public function getConfig();

  /**
   * Set the configuration for an instance.
   *
   * @param array $config
   *   Arbitrary config array, depending on the type of item.
   */
  public function setConfig(array $config);

  /**
   * Get the label of an item.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the rendered value.
   */
  public function getLabel();

  /**
   * Get the value of an item.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the rendered value.
   */
  public function getValue();

  /**
   * Get a render array for an item.
   *
   * @return array
   *   Return the value as a render array.
   */
  public function getRenderArray();

  /**
   * Get a representation fo the value that can be used for sorting.
   */
  public function getSortableValue();

  /**
   * Get the classes of an item.
   *
   * @return string[]
   *   Return the classes for an item.
   */
  public function getClasses();

  /**
   * Preview a key from the configuration.
   *
   * This should be used only during configuration steps.
   */
  public function preview($key);

  /**
   * Get an item from config by key if it exists.
   *
   * @param string $key
   *   The key to retrieve.
   *
   * @return mixed|null
   *   A value for the given key if it exists.
   */
  public function get($key);

  /**
   * Set the context for an instance.
   *
   * @param array $context
   *   Arbitrary context array, depending on the type of item.
   */
  public function setContext(array $context);

  /**
   * Get the context for an instance.
   *
   * @return array
   *   Arbitrary context array, depending on the type of item.
   */
  public function getContext();

  /**
   * Set the context value for a specific context key.
   *
   * @param string $key
   *   The key for the context value.
   * @param mixed $context
   *   Arbitrary context value.
   */
  public function setContextValue($key, $context);

  /**
   * Get the context value for a specific context key.
   *
   * @param string $key
   *   The key for the context value.
   *
   * @return mixed
   *   The value for the context key.
   */
  public function getContextValue($key);

  /**
   * Whether an instance of a plugin has a filter configured.
   *
   * @return bool
   *   TRUE if no filter is configured, FALSE otherwhise.
   */
  public function hasAppliccableFilter();

  /**
   * Build a filter form.
   *
   * @param array $element
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   A form structure array.
   */
  public function buildFilterForm(array $element, FormStateInterface $form_state);

  /**
   * Get a summary for the configured filter.
   *
   * @return string
   *   A summary string.
   */
  public function getFilterSummary();

  /**
   * Check the configured filter settings against this instance.
   *
   * @return bool
   *   TRUE if no filter is configured or if it passes, FALSE otherwhise.
   */
  public function checkFilter();

  /**
   * Check if an item represents a group.
   *
   * @return bool
   *   TRUE if the item is a group, FALSE otherwise.
   */
  public function isGroupItem();

}
