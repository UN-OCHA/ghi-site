<?php

namespace Drupal\ghi_form_elements;

use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\hpc_api\Query\EndpointQueryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for configuration container item plugins.
 */
abstract class ConfigurationContainerItemPluginBase extends PluginBase implements ConfigurationContainerItemPluginInterface {

  use StringTranslationTrait;
  use AjaxElementTrait;

  const SORT_TYPE = 'numeric';
  const DATA_TYPE = 'numeric';
  const ITEM_TYPE = 'amount';

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The manager class for endpoint query plugins.
   *
   * @var \Drupal\hpc_api\Query\EndpointQueryManager
   */
  protected $endpointQueryManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
    );
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->endpointQueryManager = $container->get('plugin.manager.endpoint_query_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    self::setElementParents($element);

    $this->wrapperId = Html::getClass(implode('-', array_merge($element['#array_parents'], [
      $this->getPluginId(),
      'container-wrapper',
    ])));
    $element['#prefix'] = '<div id="' . $this->wrapperId . '">';
    $element['#suffix'] = '</div>';

    $element['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'label'),
    ];

    if (method_exists($this, 'getDefaultLabel')) {
      $element['label']['#description'] = $this->t('Leave empty to use a default label');
      $element['label']['#placeholder'] = $this->getDefaultLabel();
    }

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
  public function getPluginLabel() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDescription() {
    $plugin_definition = $this->getPluginDefinition();
    if (empty($plugin_definition['description'])) {
      return NULL;
    }
    return $plugin_definition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    return $this->config;
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
    if (method_exists($this, 'getDefaultLabel')) {
      return $this->getDefaultLabel();
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
  public function getRenderArray() {
    return [
      '#markup' => $this->getValue(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getTableCell() {
    return [
      'data' => $this->getRenderArray() ?? ['#markup' => $this->t('n/a')],
      'data-value' => $this->getValue(),
      'data-raw-value' => $this->getSortableValue(),
      'data-sort-type' => $this::SORT_TYPE,
      'data-column-type' => $this->getColumnType(),
      'data-content' => $this->getLabel(),
      'class' => $this->getClasses(),
      'export_value' => $this->getSortableValue(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSortableValue() {
    return $this->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function getColumnType() {
    return static::ITEM_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = [
      Html::getClass($this->getPluginId()),
    ];
    $value = $this->getValue();
    if (empty($value)) {
      $classes[] = 'empty';
    }
    if ($value === NULL) {
      $classes[] = 'not-available';
    }
    return $classes;
  }

  /**
   * {@inheritdoc}
   */
  final public function preview($key) {
    // Turn key into camelcase and prefix with 'get' to build a potential
    // method name. This is primarily used for convenience functions in the
    // implementing plugins to define methods `getLabel`, `getValue` and
    // `getRenderArray`.
    $method = 'get' . implode('', array_map(function ($item) {
      return ucfirst($item);
    }, explode('_', $key)));
    if (method_exists($this, $method)) {
      return $this->{$method}();
    }
    return $this->get($key);
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
    if (array_key_exists($key, $this->config)) {
      return $this->config[$key];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($context) {
    $this->context = $context;
    $plan_object = $context['plan_object'] ?? NULL;
    if ($plan_object) {
      $query_handlers = $this->getQueryHandlers();
      foreach ($query_handlers as $query) {
        $query->setPlaceholder('plan_id', $plan_object->field_original_id->value);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  public function setContextValue($key, $context) {
    $this->context[$key] = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getContextValue($key) {
    return array_key_exists($key, $this->context) ? $this->context[$key] : NULL;
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
      if (!is_object($value) || !$value instanceof EndpointQueryPluginInterface) {
        continue;
      }
      $query_handlers[] = $value;
    }
    return $query_handlers;
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
   * @return mixed
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

  /**
   * {@inheritdoc}
   */
  public function hasAppliccableFilter() {
    $filter = $this->get('filter');
    return $filter && !empty($filter['op']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFilterForm($element, FormStateInterface $form_state) {
    $filter_config = $this->get('filter');

    $element['op'] = [
      '#type' => 'select',
      '#title' => $this->t('Operation'),
      '#options' => $this->getFilterOptions(),
      '#default_value' => !empty($filter_config['op']) ? $filter_config['op'] : NULL,
    ];

    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => is_array($filter_config) && array_key_exists('value', $filter_config) ? $filter_config['value'] : NULL,
      '#states' => [
        'invisible' => [
          ':input[name="' . FormElementHelper::getStateSelector($element, ['op']) . '"]' => [
            ['value' => 'empty'],
            ['value' => 'not_empty'],
          ],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilterSummary() {
    $filter = $this->get('filter');
    $options = $this->getFilterOptions();
    if (empty($filter) || !array_key_exists('op', $filter) || !array_key_exists($filter['op'], $options)) {
      return '-';
    }
    if ($this::DATA_TYPE == 'string') {
      return strpos($filter['op'], 'empty') === FALSE ? $options[$filter['op']] . ' "' . $filter['value'] . '"' : $options[$filter['op']];
    }
    return strpos($filter['op'], 'empty') === FALSE ? $options[$filter['op']] . ' ' . $filter['value'] : $options[$filter['op']];
  }

  /**
   * Get the filter options.
   *
   * @return array
   *   An array of filter options.
   */
  private function getFilterOptions() {
    $ops_common = [
      'equal' => $this->t('Equal'),
      'not_equal' => $this->t('Not equal'),
      'empty' => $this->t('Empty'),
      'not_empty' => $this->t('Not Empty'),
    ];
    $ops_numeric = $ops_common + [
      'greater_than' => $this->t('Greater than'),
      'less_than' => $this->t('Less than'),
    ];
    $ops_string = $ops_common + [
      'contains' => $this->t('Contains'),
    ];

    switch (get_called_class()::DATA_TYPE) {
      case 'numeric':
        return $ops_numeric;

      case 'string':
        return $ops_string;

    }
  }

  /**
   * {@inheritdoc}
   */
  public function checkFilter() {
    if (!$this->hasAppliccableFilter()) {
      // When no filter is set, this passes.
      return TRUE;
    }

    $filter = $this->get('filter');
    $value = $this->getValue();
    $result = FALSE;
    switch ($filter['op']) {
      case 'equal':
        $result = $value == $filter['value'];
        break;

      case 'not_equal':
        $result = $value != $filter['value'];
        break;

      case 'greater_than':
        $result = $value > $filter['value'];
        break;

      case 'less_than':
        $result = $value < $filter['value'];
        break;

      case 'empty':
        $result = empty($value);
        break;

      case 'not_empty':
        $result = !empty($value);
        break;

      case 'contains':
        $result = strpos($value, $filter['value']) !== FALSE;
        break;
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function isGroupItem() {
    return $this instanceof ConfigurationContainerItemGroupInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return [];
  }

}
