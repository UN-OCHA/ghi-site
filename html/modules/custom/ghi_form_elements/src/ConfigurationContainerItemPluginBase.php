<?php

namespace Drupal\ghi_form_elements;

use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hpc_api\Query\EndpointQuery;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for configuration container item plugins.
 */
abstract class ConfigurationContainerItemPluginBase extends PluginBase implements ConfigurationContainerItemPluginInterface {

  use StringTranslationTrait;
  use AjaxElementTrait;

  /**
   * Config for an instance of the item.
   *
   * @var array
   */
  protected $config = [];

  /**
   * Context for an instance of the item.
   *
   * @var array
   */
  protected $context;

  /**
   * The wrapper id for the form element.
   *
   * @var array
   */
  protected $wrapperId;

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
    self::setElementParents($element);

    $this->wrapperId = Html::getClass(implode('-', array_merge($element['#array_parents'], [
      $this->getPluginId(),
      'wrapper',
    ])));
    $element['#prefix'] = '<div id="' . $this->wrapperId . '">';
    $element['#suffix'] = '</div>';

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => array_key_exists('label', $this->config) ? $this->config['label'] : NULL,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginConfiguration() {
    return $this->configuration;
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
  public function getLabel() {
    if (array_key_exists('label', $this->config) && !empty($this->config['label'])) {
      return $this->config['label'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    if (array_key_exists('value', $this->config) && !empty($this->config['value'])) {
      return $this->config['value'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    if ($this->config === NULL) {
      return NULL;
    }
    if (is_array($key)) {
      return NestedArray::getValue($this->config, $key);
    }

    $method = 'get' . ucfirst($key);
    if (method_exists($this, $method)) {
      return $this->{$method}();
    }
    elseif (array_key_exists($key, $this->config)) {
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

  /**
   * {@inheritdoc}
   */
  public function setContext($context) {
    $this->context = $context;

    $plan_node = $context['plan_node'] ?? NULL;
    if ($plan_node && $plan_node->bundle() == 'plan') {
      $query_handlers = $this->getQueryHandlers();
      foreach ($query_handlers as $query) {
        $query->setPlaceholder('plan_id', $plan_node->field_original_id->value);
      }
    }
  }

  /**
   * Retrieve the query handlers defined for a configuration item plugin.
   *
   * @return \Drupal\hpc_api\Query\EndpointQuery[]
   *   An array of EndpointQuery objects used by a plugin.
   */
  protected function getQueryHandlers() {
    $query_handlers = [];
    $reflect = new \ReflectionClass($this);
    $properties = $reflect->getProperties();
    foreach ($properties as $property) {
      if (!$property->isPublic()) {
        continue;
      }
      $value = $property->getValue($this);
      if (!is_object($value) || !$value instanceof EndpointQuery) {
        continue;
      }
      $query_handlers[] = $value;
    }
    return $query_handlers;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * Get a submitted value from the form state.
   *
   * @param array $element
   *   The element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $value_key
   *   The value to retrieve.
   * @param mixed $default_value
   *   The default value to use.
   *
   * @return string
   *   The value key, either submitted, stored, taken from original config or
   *   the given default.
   */
  public function getSubmittedValue(array $element, FormStateInterface $form_state, $value_key, $default_value = NULL) {
    $value_parents = array_merge($element['#parents'], (array) $value_key);
    $_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
    $submitted = $_form_state->hasValue($value_parents) ? $_form_state->getValue($value_parents) : NULL;
    $stored = $_form_state->get($value_key) ?: NULL;
    $value = $submitted ?: ($stored ?: $this->get($value_key));
    return $value ?: $default_value;
  }

  /**
   * Get a submitted value from the form state.
   *
   * @param array $element
   *   The element array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $value_key
   *   The value to retrieve.
   * @param array $options
   *   An array of options.
   *
   * @return string
   *   The value key, either submitted or the first valid one from the options.
   */
  public function getSubmittedOptionsValue(array $element, FormStateInterface $form_state, $value_key, array $options) {
    $value = $this->getSubmittedValue($element, $form_state, $value_key);
    if (!$value || !array_key_exists($value, $options)) {
      $value = array_key_first($options);
      $value_parents = array_merge($element['#parents'], (array) $value_key);
      $_form_state = $form_state instanceof SubformStateInterface ? $form_state->getCompleteFormState() : $form_state;
      $_form_state->setValue($value_parents, $value);
    }
    return $value;
  }

}
