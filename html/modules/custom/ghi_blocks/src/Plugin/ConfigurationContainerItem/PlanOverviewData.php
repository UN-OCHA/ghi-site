<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides a plan overview data item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "plan_overview_data",
 *   label = @Translation("Plan overview data"),
 *   description = @Translation("This item displays data from the plan overview for a year."),
 * )
 */
class PlanOverviewData extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemValuePreviewTrait;

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $type_key = $this->getSubmittedOptionsValue($element, $form_state, 'type', $this->getTypeOptions());
    $use_custom_value = $this->getSubmittedValue($element, $form_state, 'use_custom_value');
    $custom_value = $this->getSubmittedValue($element, $form_state, 'custom_value');
    $sum = $this->getSubmittedValue($element, $form_state, 'sum');
    $footnote = $this->getSubmittedValue($element, $form_state, 'footnote');

    $type = $this->getType($type_key);

    $element['type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $this->getTypeOptions(),
      '#default_value' => $type_key,
      '#weight' => -1,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];

    $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
      '%default_label' => $this->getDefaultLabel($type),
    ]);
    $element['label']['#placeholder'] = $this->getDefaultLabel($type);
    $element['label']['#weight'] = 0;

    $element['api_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API value'),
      '#default_value' => $this->getApiValue($type),
      '#disabled' => TRUE,
    ];

    $element['use_custom_value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use a write-in value'),
      '#default_value' => $use_custom_value,
      '#description' => $this->t('Check this if you want to enter a custom write-in value.'),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];

    $checkbox_selector = FormElementHelper::getStateSelector($element, ['use_custom_value']);
    $element['custom_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Write-in value'),
      '#default_value' => $custom_value,
      '#description' => $type['value_description'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $checkbox_selector . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    if ($use_custom_value && strcmp($custom_value, $this->getCustomValue($type, $use_custom_value, $custom_value)) !== 0) {
      $element['custom_value']['#description'] .= '<br /><strong>' . $this->t('The entered value is not in the expected format and will not be used.') . '</strong>';
    }

    $element['sum'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sum API and write-in value'),
      '#default_value' => $sum,
      '#description' => $this->t('Check this if you want that the API value and the non-empty write-in value will be summed up before display.'),
      '#access' => !empty($type['allow_sum']),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $checkbox_selector . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $element['footnote'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Footnote'),
      '#default_value' => array_key_exists('footnote', $this->config) ? $this->config['footnote'] : NULL,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];

    $element['value_preview'] = $this->buildValuePreviewFormElement($this->getRenderArray($type, $use_custom_value, $custom_value, $sum, $footnote));

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    return $this->getDefaultLabel();
  }

  /**
   * Provide a default label for the current item.
   *
   * @param array|null $data_type
   *   The data type as optional parameter.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel($data_type = NULL) {
    $data_type = $data_type ?? $this->getType();
    return $data_type['default_label'] ?? ($data_type['label'] ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($type = NULL, $use_custom_value = NULL, $custom_value = NULL, $sum = NULL) {
    $type = $type ?? $this->getType();
    $use_custom_value = $use_custom_value ?? $this->get('use_custom_value');
    $custom_value = $custom_value ?? $this->get('custom_value');
    $sum = ($sum ?? $this->get('sum')) && !empty($type['allow_sum']);

    $api_value = $this->getApiValue($type);
    $custom_value = $this->getCustomValue($type, $use_custom_value, $custom_value);
    return $sum ? $api_value + $custom_value : ($custom_value ?? $api_value);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray($type = NULL, $use_custom_value = NULL, $custom_value = NULL, $sum = NULL, $footnote = NULL) {
    $type = $type ?? $this->getType();
    $use_custom_value = $use_custom_value ?? $this->get('use_custom_value');
    $custom_value = $custom_value ?? $this->get('custom_value');
    $sum = ($sum ?? $this->get('sum')) && !empty($type['allow_sum']);
    $footnote = $footnote ?? $this->get('footnote');

    $theme = $type['theme'] ?? 'hpc_amount';
    $value = $this->getValue($type, $use_custom_value, $custom_value, $sum);
    if ($theme == 'hpc_percent') {
      $value = $value * 100;
    }
    $build = ThemeHelper::getThemeOptions($theme, $value, [
      'decimals' => $theme == 'hpc_amount' ? 1 : 2,
    ]);

    if ($footnote) {
      $build = [
        '#type' => 'container',
        0 => $build,
        'tooltips' => [
          '#theme' => 'hpc_tooltip_wrapper',
          '#tooltips' => [
            '#theme' => 'hpc_tooltip',
            '#tooltip' => [
              '#plain_text' => $footnote,
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiValue($type = NULL) {
    $type = $type ?? $this->getType();
    $data = $this->getContextValue('data');
    $value = $data[$type['id']] ?? 0;
    return $value;
  }

  /**
   * Get the operation to apply to the values.
   *
   * @param array $type
   *   The type definition, see self::getTypes().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The value operation or NULL.
   */
  public function getValueOperation(?array $type = NULL) {
    $type = $type ?? $this->getType();
    $custom_value = $this->getCustomValue();
    $sum = ($sum ?? $this->get('sum')) && !empty($type['allow_sum']);
    if ($sum && $custom_value) {
      return $this->t('Sum');
    }
    elseif ($custom_value) {
      return $this->t('Overwrite');
    }
    return NULL;
  }

  /**
   * Get the write-in value for the item.
   *
   * @param array $type
   *   The type definition, see self::getTypes().
   * @param bool $use_custom_value
   *   Whether a write-in value should actually be used.
   * @param mixed $custom_value
   *   The value entered by the user. The format should be one of the data
   *   types in use by the defined types.
   *
   * @return mixed
   *   The type-casted write-in value.
   */
  public function getCustomValue(?array $type = NULL, $use_custom_value = NULL, $custom_value = NULL) {
    $type = $type ?? $this->getType();
    $use_custom_value = $use_custom_value ?? $this->get('use_custom_value');
    $custom_value = $custom_value ?? $this->get('custom_value');

    if (!$use_custom_value) {
      return NULL;
    }
    switch ($type['data_type']) {
      case 'integer':
        return (int) $custom_value;

      case 'float':
        return (float) $custom_value;
    }
    return NULL;
  }

  /**
   * Get the options for the type selector.
   *
   * @return array
   *   Array with type ids as keys and the type labels as values.
   */
  private function getTypeOptions() {
    $types = $this->getTypes();
    return array_map(function ($type) {
      return $type['label'];
    }, $types);
  }

  /**
   * Get the available types for an element.
   *
   * @return array
   *   An array of types.
   */
  private function getTypes() {
    $types = [
      'people_in_need' => [
        'label' => $this->t('People in need'),
        'value_description' => $this->t('Enter amount as full integer to override the value from the API.'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_amount',
      ],
      'people_target' => [
        'label' => $this->t('People targeted'),
        'value_description' => $this->t('Enter amount as full integer to override the value from the API.'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_amount',
      ],
      'people_expected_reach' => [
        'label' => $this->t('People expected reach'),
        'value_description' => $this->t('Enter amount as full integer to override the value from the API.'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_amount',
      ],
      'people_reached' => [
        'label' => $this->t('People latest reached'),
        'value_description' => $this->t('Enter amount as full integer to override the value from the API.'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_amount',
      ],
      'people_reached_percent' => [
        'label' => $this->t('People reached (%)'),
        'value_description' => $this->t('Enter ratio as decimal between 0 and 1.'),
        'data_type' => 'float',
        'theme' => 'hpc_percent',
      ],
      'total_funding' => [
        'label' => $this->t('Total funding'),
        'value_description' => $this->t('Enter a write-in value to override the value from the API. <b>Note that write-in values are ignored if you limit the data to include or exclude a global plan.</b>'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_currency',
      ],
      'total_requirements' => [
        'label' => $this->t('Total requirements'),
        'value_description' => $this->t('Enter a write-in value to override the value from the API. <b>Note that write-in values are ignored if you limit the data to include or exclude a global plan.</b>'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_currency',
      ],
      'funding_progress' => [
        'label' => $this->t('Funding coverage (%)'),
        'default_label' => $this->t('% Funded'),
        'value_description' => $this->t('Enter a percentage value as a decimal between 0 and 100. <strong>Note that write-in values are ignored if you limit the data to include or exclude a global plan.</strong>'),
        'data_type' => 'float',
        'theme' => 'hpc_percent',
      ],
      'countries_affected' => [
        'label' => $this->t('Countries affected'),
        'data_type' => 'integer',
        'allow_sum' => TRUE,
        'theme' => 'hpc_amount',
      ],
    ];
    $configuration = $this->getPluginConfiguration();
    if (array_key_exists('item_types', $configuration)) {
      $types = array_intersect_key($types, $configuration['item_types']);
      foreach ($types as $type_key => &$type) {
        $type = $configuration['item_types'][$type_key] + $type;
        $type['id'] = $type_key;
      }
    }
    return $types;
  }

  /**
   * Get a specific data type definition.
   *
   * @param string $type
   *   The key of the type.
   *
   * @return array|null
   *   A definition array if the type is found.
   */
  private function getType($type = NULL) {
    if ($type === NULL) {
      $type = $this->config['type'] ?? NULL;
    }
    $types = $this->getTypes();
    return $type && array_key_exists($type, $types) ? $types[$type] : NULL;
  }

}
