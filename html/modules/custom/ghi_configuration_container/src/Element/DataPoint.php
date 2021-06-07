<?php

namespace Drupal\ghi_configuration_container\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_configuration_container\Traits\AjaxElementTrait;

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
    $context = $element['#element_context'];
    $attachment = $element['#attachment'];
    if (empty($context['attachment_query'])) {
      return $element;
    }

    // Set the defaults.
    $values = (array) $form_state->getValue($element['#parents']) + (array) $element['#default_value'];
    $defaults = [
      'processing' => !empty($values['processing']) ? $values['processing'] : NULL,
      'calculation' => !empty($values['calculation']) ? $values['calculation'] : NULL,
      'data_points' => [
        0 => !empty($values['data_points'][0]) ? $values['data_points'][0] : NULL,
        1 => !empty($values['data_points'][1]) ? $values['data_points'][1] : NULL,
      ],
      'formatting' => !empty($values['formatting']) ? $values['formatting'] : NULL,
      'widget' => !empty($values['widget']) ? $values['widget'] : NULL,
    ];

    $element['processing'] = [
      '#type' => 'select',
      '#title' => t('Processing'),
      '#options' => self::getProcessingOptions(),
      '#default_value' => $defaults['processing'],
    ];

    $processing_selector = reset($element['#parents']) . '[' . implode('][', array_merge(array_slice($element['#parents'], 1), ['processing'])) . ']';
    $element['calculation'] = [
      '#type' => 'select',
      '#title' => t('Calculation'),
      '#options' => self::getCalculationOptions(),
      '#default_value' => $defaults['calculation'],
      '#states' => [
        'visible' => [
          'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
        ],
      ],
    ];

    $element['data_points'] = [];
    $element['data_points'][0] = [
      '#type' => 'select',
      '#title' => t('Data point'),
      '#options' => $attachment->prototype->fields,
      '#default_value' => $defaults['data_points'][0],
    ];
    $element['data_points'][1] = [
      '#type' => 'select',
      '#title' => t('Data point (2)'),
      '#options' => $attachment->prototype->fields,
      '#default_value' => $defaults['data_points'][1],
      '#states' => [
        'visible' => [
          'select[name="' . $processing_selector . '"]' => ['value' => 'calculated'],
        ],
      ],
    ];

    $element['formatting'] = [
      '#type' => 'select',
      '#title' => t('Formatting'),
      '#options' => self::getFormattingOptions(),
      '#default_value' => $defaults['formatting'],
    ];
    $element['widget'] = [
      '#type' => 'select',
      '#title' => t('Mini widget'),
      '#options' => self::getWidgetOptions(),
      '#default_value' => $defaults['widget'],
    ];

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
   * Get an array of processing options.
   *
   * @return array
   *   The options array.
   */
  private static function getProcessingOptions() {
    return [
      'single' => t('Single data point'),
      'calculated' => t('Calculated from 2 data points'),
    ];
  }

  /**
   * Get an array of calculation options.
   *
   * @return array
   *   The options array.
   */
  private static function getCalculationOptions() {
    return [
      'addition' => t('Sum (data point 1 + data point 2)'),
      'substraction' => t('Substraction (data point 1 - data point 2)'),
      'division' => t('Division (data point 1 / data point 2)'),
      'percentage' => t('Percentage (data point 1 * (100 / data point 2))'),
    ];
  }

  /**
   * Get an array of formatting options.
   *
   * @return array
   *   The options array.
   */
  private static function getFormattingOptions() {
    return [
      'raw' => t('Raw data (no formatting)'),
      'auto' => t('Automatic based on the unit (uses percentage for percentages, amount for all others)'),
      'currency' => t('Currency value'),
      'amount' => t('Amount value'),
      'amount_rounded' => t('Amount value (rounded, 1 decimal)'),
      'percent' => t('Percentage value'),
    ];
  }

  /**
   * Get an array of widget options.
   *
   * @return array
   *   The options array.
   */
  private static function getWidgetOptions() {
    return [
      'none' => t('None'),
      'progressbar' => t('Progress bar'),
      'pie_chart' => t('Pie chart'),
      'spark_line' => t('Spark line'),
    ];
  }

}
