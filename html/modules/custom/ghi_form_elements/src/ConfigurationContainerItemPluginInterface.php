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

}
