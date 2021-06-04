<?php

namespace Drupal\ghi_plans\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_configuration_container\ConfigurationContainerItemPluginBase;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides an funding data item for configuration containers.
 *
 * @todo This is still missing support for special requirements logic.
 * @todo This is still missing support for cluster filters.
 *
 * @ConfigurationContainerItem(
 *   id = "funding_data",
 *   label = @Translation("Financial data"),
 * )
 */
class FundingData extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $data_type_options = $this->getDataTypeOptions();
    $data_type_key = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);
    $scale = $this->getSubmittedValue($element, $form_state, 'scale', 'auto');

    $element['data_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $data_type_options,
      '#default_value' => $data_type_key,
      '#weight' => 0,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
    $element['label']['#weight'] = 1;

    $data_type = $this->getDataType($data_type_key);
    if ($data_type && !empty($data_type['default_label'])) {
      $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
        '%default_label' => (string) $data_type['default_label'],
      ]);
      $element['label']['#placeholder'] = (string) $data_type['default_label'];
    }
    else {
      $element['label']['#required'] = TRUE;
    }

    $element['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale'),
      '#options' => [
        'auto' => $this->t('Automatic'),
        'full' => $this->t('Full value'),
      ],
      '#default_value' => $scale,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 2,
    ];

    if ($data_type && !empty($data_type['scale'])) {
      $element['scale']['#type'] = 'hidden';
      $element['scale']['#value'] = $data_type['scale'];
      $element['scale']['#default_value'] = $data_type['scale'];
    }

    // Add a preview.
    $element['value_preview'] = [
      '#type' => 'item',
      '#title' => $this->t('Value preview'),
      '#markup' => $this->getValue($data_type_key, $scale),
      '#weight' => 3,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    $data_type_key = $this->get('data_type');
    $data_type = $this->getDataType($data_type_key);
    return $data_type['default_label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($data_type_key = NULL, $scale = NULL) {
    $context = $this->getContext();
    $page_node = $context['page_node'];

    if ($data_type_key === NULL) {
      $data_type_key = $this->get('data_type');
    }
    $data_type = $this->getDataType($data_type_key);

    if ($scale === NULL) {
      $scale = $this->get('scale');
    }
    $scale = $scale ?: (!empty($data_type['scale']) ? $data_type['scale'] : 'auto');
    $value = NULL;
    if ($page_node->bundle() == 'plan') {
      $value = $context['funding_summary_query']->get($data_type['property'], 0);
    }

    $theme_function = !empty($data_type['theme']) ? $data_type['theme'] : 'hpc_currency';
    return ThemeHelper::theme($theme_function, ThemeHelper::getThemeOptions($theme_function, $value, [
      'scale' => $scale,
      'formatting_decimals' => $context['plan_node']->field_decimal_format->value,
    ]));
  }

  /**
   * Get the data type options.
   *
   * @return array
   *   An array of data types, suitable to use as options in a form element.
   */
  private function getDataTypeOptions() {
    $context = $this->getContext();
    $page_node = $context['page_node'];
    $data_types = array_filter($this->getDataTypes(), function ($type) use ($page_node) {
      return !array_key_exists('valid_context', $type) || in_array($page_node->bundle(), $type['valid_context']);
    });
    return array_map(function ($type) {
      return $type['title'];
    }, $data_types);
  }

  /**
   * Get the available data types.
   *
   * @return array
   *   An array of defined data types.
   */
  private function getDataTypes() {
    return [
      'funding_totals' => [
        'title' => $this->t('Funding totals'),
        'default_label' => $this->t('Current funding ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'total_funding',
        'scale' => 'auto',
      ],
      'outside_funding' => [
        'title' => $this->t('Funded outside HRP'),
        'default_label' => $this->t('Funded outside HRP ($)'),
        'valid_context' => ['plan'],
        'cluster_restrict' => FALSE,
        'property' => 'outside_funding',
        'scale' => 'auto',
      ],
      'funding_coverage' => [
        'title' => $this->t('Funding coverage'),
        'default_label' => $this->t('Coverage (%)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_coverage',
        'scale' => 'auto',
        'theme' => 'hpc_percent',
      ],
      'funding_gap' => [
        'title' => $this->t('Funding gap'),
        'default_label' => $this->t('Unmet ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_gap',
        'scale' => 'auto',
      ],
      'original_requirements' => [
        'title' => $this->t('Original requirements'),
        'default_label' => $this->t('Original ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'original_requirements',
      ],
      'current_requirements' => [
        'title' => $this->t('Current requirements'),
        'default_label' => $this->t('Requirements ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'current_requirements',
      ],
    ];
  }

  /**
   * Get a specific data type definition.
   *
   * @param string $data_type
   *   The key of the data type.
   *
   * @return array|null
   *   A definition array if the data type is found.
   */
  private function getDataType($data_type) {
    $data_types = $this->getDataTypes();
    return array_key_exists($data_type, $data_types) ? $data_types[$data_type] : NULL;
  }

}
