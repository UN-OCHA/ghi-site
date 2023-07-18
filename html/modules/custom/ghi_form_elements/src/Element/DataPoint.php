<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides a data point element.
 *
 * @FormElement("data_point")
 */
class DataPoint extends FormElement {

  use AjaxElementTrait;

  /**
   * Global switch for widget support in data points.
   */
  const WIDGET_SUPPORT = FALSE;

  /**
   * Default value for the calculation method checkbox.
   *
   * Applies only to measurement data points on indicator attachments.
   */
  const CALCULATION_METHOD_DEFAULT = TRUE;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processDataPoint'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderDataPoint'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#attachment' => NULL,
      '#attachment_prototype' => NULL,
      '#plan_object' => NULL,
      '#select_monitoring_period' => FALSE,
      '#widget' => self::WIDGET_SUPPORT,
      '#hidden' => FALSE,
      '#disabled_empty_fields' => TRUE,
      '#wrapper_id' => NULL,
      // Preset options.
      '#presets' => [],
    ];
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   *
   * @todo Check if this is actually needed.
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== NULL) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    return NULL;
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processDataPoint(array &$element, FormStateInterface $form_state) {
    $attachment = $element['#attachment'];
    $plan_object = $element['#plan_object'] ?? NULL;
    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $attachment ? $attachment->prototype : $element['#attachment_prototype'];
    if (empty($attachment) && empty($attachment_prototype)) {
      return $element;
    }

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    // Set the defaults.
    $values = (array) $form_state->getValue($element['#parents']) + (array) $element['#default_value'];
    $defaults = [
      'processing' => !empty($values['processing']) ? $values['processing'] : array_key_first(DataAttachment::getProcessingOptions()),
      'calculation' => !empty($values['calculation']) ? $values['calculation'] : NULL,
      'data_points' => [
        0 => $values['data_points'][0] ?? [
          'index' => array_key_first($attachment_prototype->getFields()),
          'use_calculation_method' => NULL,
        ],
        1 => array_key_exists('data_points', $values) && array_key_exists(1, $values['data_points']) ? $values['data_points'][1] : NULL,
      ],
      'formatting' => !empty($values['formatting']) ? $values['formatting'] : array_key_first(DataAttachment::getFormattingOptions()),
      'widget' => !empty($values['widget']) ? $values['widget'] : 'none',
    ];

    $element['processing'] = [
      '#type' => 'select',
      '#title' => t('Type'),
      '#options' => DataAttachment::getProcessingOptions(),
      '#default_value' => $defaults['processing'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $processing_selector = FormElementHelper::getStateSelector($element, ['processing']);
    $element['calculation'] = [
      '#type' => 'select',
      '#title' => t('Calculation'),
      '#options' => DataAttachment::getCalculationOptions(),
      '#default_value' => $defaults['calculation'],
      '#states' => [
        'visible' => [
          'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
        ],
      ],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $data_point_options = self::getDataPointOptions($element);

    $element['data_points'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['data-points-wrapper'],
      ],
    ];
    $element['data_points'][0] = [
      '#type' => 'container',
    ];
    $element['data_points'][1] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
        ],
      ],
    ];
    $element['data_points'][0]['index'] = [
      '#type' => 'select',
      '#title' => t('Data point'),
      '#options' => $data_point_options,
      '#default_value' => $defaults['data_points'][0]['index'] ?? NULL,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $data_point_selector = FormElementHelper::getStateSelector($element, [
      'data_points',
      0,
      'index',
    ]);
    $measurement_fields = $attachment_prototype->getMeasurementMetricFields();
    if ($attachment_prototype->isIndicator()) {
      $element['data_points'][0]['use_calculation_method'] = [
        '#type' => 'checkbox',
        '#title' => t('Use calculation method'),
        '#default_value' => $defaults['data_points'][0]['use_calculation_method'] ?? self::CALCULATION_METHOD_DEFAULT,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#states' => [
          'visible' => [
            'select[name="' . $data_point_selector . '"]' => array_map(function ($value) {
              return ['value' => $value];
            }, array_keys($measurement_fields)),
          ],
        ],
      ];
      if ($attachment && $attachment instanceof IndicatorAttachment) {
        $element['data_points'][0]['use_calculation_method']['#title'] .= ' (' . $attachment->getCalculationMethod() . ')';
      }

      // It's a difficult to find out here if this part of the form has already
      // been submitted. What seems to work ok is to look at the value of the
      // submitted checkbox and the index of the second data point.
      $input = $form_state->getUserInput();
      $submitted = NestedArray::getValue($input, array_merge($element['#parents'], ['data_points']));
      if ($submitted[0]['use_calculation_method'] === NULL && $defaults['data_points'][1]['index'] == '' && self::CALCULATION_METHOD_DEFAULT) {
        // Due to a bug with checkbox elements in ajax contexts, the default
        // value is not correctly set for new instances of a plugin. We catch
        // this situation by manually setting the checked attribute only if the
        // config key is still unset.
        // Might relate to https://www.drupal.org/project/drupal/issues/1100170.
        $element['data_points'][0]['use_calculation_method']['#attributes']['checked'] = 'checked';
      }
    }
    if (!empty($element['#select_monitoring_period'])) {
      $element['data_points'][0]['monitoring_period'] = [
        '#type' => 'monitoring_period',
        '#title' => t('Monitoring period'),
        '#default_value' => $defaults['data_points'][0]['monitoring_period'] ?? NULL,
        '#plan_id' => $plan_object->getSourceId(),
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#states' => [
          'visible' => [
            'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
          ],
        ],
      ];
    }
    $element['data_points'][1]['index'] = [
      '#type' => 'select',
      '#title' => t('Data point (2)'),
      '#options' => $data_point_options,
      '#default_value' => $defaults['data_points'][1]['index'] ?? NULL,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    $data_point_selector_1 = FormElementHelper::getStateSelector($element, [
      'data_points',
      1,
      'index',
    ]);
    if ($attachment_prototype->isIndicator()) {
      $element['data_points'][1]['use_calculation_method'] = [
        '#type' => 'checkbox',
        '#title' => t('Use calculation method'),
        '#default_value' => $defaults['data_points'][1]['use_calculation_method'] ?? self::CALCULATION_METHOD_DEFAULT,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#states' => [
          'visible' => [
            'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
            'select[name="' . $data_point_selector_1 . '"]' => array_map(function ($value) {
              return ['value' => $value];
            }, array_keys($measurement_fields)),
          ],
        ],
      ];
      if ($attachment && $attachment instanceof IndicatorAttachment) {
        $element['data_points'][1]['use_calculation_method']['#title'] .= ' (' . $attachment->getCalculationMethod() . ')';
      }

      // It's difficult to find out here if this part of the form has already
      // been submitted. What seems to work ok is to look at the value of the
      // submitted checkbox and the index of the second data point.
      $input = $form_state->getUserInput();
      $submitted = NestedArray::getValue($input, array_merge($element['#parents'], ['data_points']));
      if ($submitted[1]['use_calculation_method'] === NULL && $defaults['data_points'][1]['index'] == '' && self::CALCULATION_METHOD_DEFAULT) {
        // Due to a bug with checkbox elements in ajax contexts, the default
        // value is not correctly set for new instances of a plugin. We catch
        // this situation by manually setting the checked attribute only if the
        // config key is still unset.
        // Might relate to https://www.drupal.org/project/drupal/issues/1100170.
        $element['data_points'][1]['use_calculation_method']['#attributes']['checked'] = 'checked';
      }
    }
    if (!empty($element['#select_monitoring_period'])) {
      $element['data_points'][1]['monitoring_period'] = [
        '#type' => 'monitoring_period',
        '#title' => t('Monitoring period (2)'),
        '#default_value' => $defaults['data_points'][1]['monitoring_period'] ?? NULL,
        '#plan_id' => $plan_object->getSourceId(),
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#states' => [
          'visible' => [
            'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
          ],
        ],
      ];
    }

    $element['formatting'] = [
      '#type' => 'select',
      '#title' => t('Formatting'),
      '#options' => DataAttachment::getFormattingOptions(),
      '#default_value' => $defaults['formatting'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $element['widget'] = [
      '#type' => 'select',
      '#title' => t('Mini widget'),
      '#options' => DataAttachment::getWidgetOptions(),
      '#default_value' => $defaults['widget'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#access' => !empty($element['#widget']),
    ];

    // Add a preview if we have an attachment.
    if (!empty($attachment)) {
      $build = $attachment->formatValue($defaults);
      $element['value_preview'] = [
        '#type' => 'item',
        '#title' => t('Value preview'),
        '#markup' => ThemeHelper::render($build, FALSE),
        '#access' => empty($element['#hidden']),
      ];
    }

    if (!empty($element['#hidden'])) {
      self::hideAllElements($element);
    }

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderDataPoint(array $element) {
    $element['#attributes']['type'] = 'data_point';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-data-point']);
    return $element;
  }

  /**
   * Assemble the options array for a datapoint.
   */
  public static function getDataPointOptions($element) {
    $attachment = $element['#attachment'];
    $attachment_prototype = $attachment ? $attachment->prototype : $element['#attachment_prototype'];
    if (empty($element['#disable_empty_fields']) || empty($attachment)) {
      return $attachment_prototype->fields;
    }
    $options = [];
    foreach ($attachment_prototype->fields as $key => $field) {
      if ($attachment->values[$key] === NULL) {
        $options[$field] = [];
      }
      else {
        $options[$key] = $field;
      }
    }
    return $options;
  }

}
