<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides a configuration container element.
 *
 * @FormElement("data_point")
 */
class DataPoint extends FormElement {

  use AjaxElementTrait;

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
      '#widget' => TRUE,
      '#hidden' => FALSE,
      '#disabled_empty_fields' => TRUE,
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
      'processing' => !empty($values['processing']) ? $values['processing'] : array_key_first(DataPointHelper::getProcessingOptions()),
      'calculation' => !empty($values['calculation']) ? $values['calculation'] : NULL,
      'data_points' => [
        0 => array_key_exists(0, $values['data_points']) ? $values['data_points'][0] : array_key_first($attachment_prototype->fields),
        1 => array_key_exists(1, $values['data_points']) ? $values['data_points'][1] : NULL,
      ],
      'formatting' => !empty($values['formatting']) ? $values['formatting'] : array_key_first(DataPointHelper::getFormattingOptions()),
      'widget' => !empty($values['widget']) ? $values['widget'] : 'none',
    ];

    $element['processing'] = [
      '#type' => 'select',
      '#title' => t('Processing'),
      '#options' => DataPointHelper::getProcessingOptions(),
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
      '#options' => DataPointHelper::getCalculationOptions(),
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
    ];
    $element['data_points'][0] = [
      '#type' => 'select',
      '#title' => t('Data point'),
      '#options' => $data_point_options,
      // '#name' => 'data-point-0',
      '#default_value' => $defaults['data_points'][0],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    $element['data_points'][1] = [
      '#type' => 'select',
      '#title' => t('Data point (2)'),
      '#options' => $data_point_options,
      // '#name' => 'data-point-1',
      '#default_value' => $defaults['data_points'][1],
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

    $element['formatting'] = [
      '#type' => 'select',
      '#title' => t('Formatting'),
      '#options' => DataPointHelper::getFormattingOptions(),
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
      '#options' => DataPointHelper::getWidgetOptions(),
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
      $build = DataPointHelper::formatValue($attachment, $defaults);
      $element['value_preview'] = [
        '#type' => 'item',
        '#title' => t('Value preview'),
        '#markup' => ThemeHelper::render($build),
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
