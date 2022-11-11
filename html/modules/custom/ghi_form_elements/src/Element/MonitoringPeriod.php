<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\Select;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides a monitoring period form element.
 *
 * @FormElement("monitoring_period")
 */
class MonitoringPeriod extends Select {

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
      '#sort_options' => FALSE,
      '#sort_start' => NULL,
      '#include_latest' => TRUE,
      '#include_none' => FALSE,
      '#default_all' => FALSE,
      '#process' => [
        [$class, 'processMonitoringPeriod'],
        [$class, 'processSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderMonitoringPeriod'],
        [$class, 'preRenderSelect'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme' => $info['#theme'],
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
  public static function processMonitoringPeriod(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $monitoring_period_options = self::getReportingPeriodOptions($element['#plan_id']);
    if ($element['#include_latest']) {
      $monitoring_period_options['latest'] = t('Latest published');
    }
    // @todo Check if this is still needed. It was introduced in HPC-7216 to
    // solve a specific problem about maps using only base metrics.
    if ($element['#include_none']) {
      $monitoring_period_options['none'] = t('None (Base metrics)');
    }

    $monitoring_period_options = array_reverse($monitoring_period_options, TRUE);

    $empty_default = $element['#multiple'] ? [array_key_first($monitoring_period_options)] : array_key_first($monitoring_period_options);
    if (!$element['#default_value'] && $element['#multiple'] && $element['#default_all']) {
      $empty_default = array_keys($monitoring_period_options);
    }

    $element = [
      '#type' => 'select',
      '#options' => $monitoring_period_options,
      '#default_value' => $element['#default_value'] ?? $empty_default,
      '#multiple' => $element['#multiple'],
    ] + $element;
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderMonitoringPeriod(array $element) {
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
      return ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $period,
      ], FALSE);
    }, $monitoring_periods);
  }

}
