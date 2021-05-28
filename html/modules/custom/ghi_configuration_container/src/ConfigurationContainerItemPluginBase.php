<?php

namespace Drupal\ghi_configuration_container;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for configuration container item plugins.
 */
abstract class ConfigurationContainerItemPluginBase extends PluginBase implements ConfigurationContainerItemPluginInterface {

  use StringTranslationTrait;

  /**
   * Config for an instance of the item.
   *
   * @var array
   */
  protected $config;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->getLabel(),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfig($config) {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if (array_key_exists($key, $this->config)) {
      return $this->config[$key];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

}
