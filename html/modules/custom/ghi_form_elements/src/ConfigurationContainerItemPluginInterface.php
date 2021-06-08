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
   * Get the label for the plugin.
   */
  public function getPluginLabel();

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
   * Set the configuration for an instance.
   *
   * @param array $config
   *   Arbitrary config array, depending on the type of item.
   */
  public function setConfig(array $config);

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
   * Get the plugin configuration for an instance.
   *
   * @return array
   *   Plugin configuration array.
   */
  public function getPluginConfiguration();

}
