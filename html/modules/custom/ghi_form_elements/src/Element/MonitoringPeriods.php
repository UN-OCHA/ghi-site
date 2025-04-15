<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Checkboxes;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;

/**
 * Provides a monitoring periods form element.
 */
#[FormElement('monitoring_periods')]
class MonitoringPeriods extends Checkboxes {

  use AjaxElementTrait;
  use PlanReportingPeriodTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#plan_id' => NULL,
      '#include_latest' => TRUE,
      '#include_none' => FALSE,
      '#default_all' => FALSE,
      '#process' => [
        [$class, 'processMonitoringPeriods'],
        [$class, 'processCheckboxes'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderMonitoringPeriods'],
        [$class, 'preRenderCompositeFormElement'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => $info['#theme_wrappers'],
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
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processMonitoringPeriods(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $monitoring_period_options = self::getReportingPeriodOptions($element['#plan_id']);
    if ($element['#include_latest']) {
      $monitoring_period_options['latest'] = (string) t('Latest published');
    }
    // @todo Check if this is still needed. It was introduced in HPC-7216 to
    // solve a specific problem about maps using only base metrics.
    if ($element['#include_none']) {
      $monitoring_period_options['none'] = (string) t('None (Base metrics)');
    }

    $element['#default_value'] = !empty(array_filter($element['#default_value'] ?? [])) ? array_combine(array_values($element['#default_value']), array_values($element['#default_value'])) : [];

    $monitoring_period_options = array_reverse($monitoring_period_options, TRUE);
    $first_monitoring_period = array_key_first($monitoring_period_options);
    $empty_default = $first_monitoring_period ? [$first_monitoring_period => (string) $first_monitoring_period] : [];

    if (empty($element['#default_value']) && $element['#default_all']) {
      $empty_default = array_combine(array_keys($monitoring_period_options), array_keys($monitoring_period_options));
    }
    $element = [
      '#type' => 'checkboxes',
      '#options' => $monitoring_period_options,
      '#default_value' => !empty($element['#default_value']) ? $element['#default_value'] : $empty_default,
      '#multiple' => TRUE,
      '#required' => $element['#required'],
    ] + $element;

    // There are issues with mutli-step forms, e.g. in configuration container
    // flows, where the default checkboxes are not checked because
    // \Drupal\Core\Render\Element\Checkbox::processCheckbox() looks at the
    // submitted values to determine the checked state.
    // Not sure this is a good way, but it seems to work to manually set the
    // input data in these cases.
    if (empty($element['#value'])) {
      $element['#value'] = $element['#default_value'];
      $input = $form_state->getUserInput();
      NestedArray::setValue($input, $element['#parents'], $element['#default_value']);
      $form_state->setUserInput($input);
    }
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderMonitoringPeriods(array $element) {
    $element['#attributes']['type'] = 'monitoring_period';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-monitoring-period']);
    return $element;
  }

  /**
   * Get the attachment query service.
   *
   * @return array
   *   An array of options for the monitoring periods.
   */
  public static function getReportingPeriodOptions($plan_id) {
    $monitoring_periods = self::getPlanReportingPeriods($plan_id, TRUE);
    return array_map(function ($period) {
      return $period->format('#@period_number: @date_range');
    }, $monitoring_periods);
  }

}
