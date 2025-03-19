<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Abstraction for API data attachment objects.
 */
class IndicatorAttachment extends DataAttachment {

  /**
   * Define calculation methods.
   *
   * Note that the API supports an additional calculation method "manual" that
   * is not supported here.
   */
  const CALCULATION_METHOD_SUM = 'sum';
  const CALCULATION_METHOD_AVERAGE = 'average';
  const CALCULATION_METHOD_MAXIMUM = 'maximum value';
  const CALCULATION_METHOD_LATEST = 'latest value';

  /**
   * {@inheritdoc}
   */
  public function getSingleValue($index, ?array $reporting_periods = NULL, $data_point_conf = []) {
    $monitoring_period = $data_point_conf['monitoring_period'] ?? 'latest';
    $reporting_periods = $this->getReportingPeriods($reporting_periods, $monitoring_period);
    if (!$this->isApiCalculated($index, $data_point_conf)) {
      $monitoring_period = !empty($reporting_periods) ? array_key_last($reporting_periods) : $monitoring_period;
      return $this->getValueForDataPoint($index, $monitoring_period);
    }
    $value = NULL;
    $values = $this->getValuesForAllReportingPeriods($index, FALSE, TRUE, $reporting_periods);
    if (empty($values)) {
      return $value;
    }
    $calculation_method = $this->getCalculationMethod();
    switch ($calculation_method) {
      case self::CALCULATION_METHOD_SUM:
        $value = array_sum($values);
        break;

      case self::CALCULATION_METHOD_AVERAGE:
        $value = array_sum($values) / count($values);
        break;

      case self::CALCULATION_METHOD_MAXIMUM:
        $value = max($values);
        break;

      case self::CALCULATION_METHOD_LATEST:
        $value = end($values);
        break;
    }
    return $value;
  }

  /**
   * Get the calculation method.
   *
   * @return string
   *   The calculation method as a string.
   */
  public function getCalculationMethod() {
    $calculation_method = $this->calculation_method;
    $prototype = $this->getPrototypeData();
    $available_methods = $prototype->getCalculationMethods();
    return in_array($calculation_method, $available_methods) ? $calculation_method : self::CALCULATION_METHOD_LATEST;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTooltip($conf) {
    $tooltip = parent::getTooltip($conf);

    // Get the last published monitoring period based on the selected periods
    // if any.
    $monitoring_period = $conf['data_points'][0]['monitoring_period'] ?? 'latest';
    $reporting_periods = $this->getReportingPeriods(NULL, $monitoring_period);
    $last_reporting_period = end($reporting_periods);
    if (!$last_reporting_period) {
      return $tooltip;
    }

    $index = $conf['data_points'][0]['index'];
    $value = $this->getSingleValue($index, NULL, $conf['data_points'][0]);

    if ($this->isNullValue($value)) {
      return $tooltip;
    }

    if ($this->isMeasurement($conf)) {
      // Otherwise see if this is a measurement and if we can get a formatted
      // monitoring period for this data point.
      $tooltip['monitoring_period'] = $this->formatMonitoringPeriod('icon', $last_reporting_period->id(), 'as of date @end_date', ['langcode' => $this->getPlanLanguage()]);
    }

    if ($this->isApiCalculated($index, $conf['data_points'][0]) && $conf['processing'] != 'calculated') {
      $tooltip = [
        'monitoring_period' => $this->formatCalculationTooltip($last_reporting_period),
      ] + $tooltip;
      $tooltip = array_filter($tooltip);
    }
    return $tooltip;
  }

  /**
   * Get a formatted calculation tooltip.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod $monitoring_period
   *   The monitoring period.
   *
   * @return array|null
   *   Either a build array for the tooltip, or NULL.
   */
  public function formatCalculationTooltip($monitoring_period) {
    $tooltip_icon = NULL;
    $tooltip_text = NULL;
    $reporting_period_text = ThemeHelper::render([
      '#theme' => 'hpc_reporting_period',
      '#reporting_period' => $monitoring_period,
      '#format_string' => ', as of date @end_date',
    ], FALSE);
    $calculation_method = $this->getCalculationMethod();
    $t_options = ['langcode' => $this->getPlanLanguage()];
    switch ($calculation_method) {
      case self::CALCULATION_METHOD_SUM:
        $tooltip_text = $this->t('This value is the sum of all monitoring periods values', [], $t_options);
        $tooltip_icon = 'functions';
        break;

      case self::CALCULATION_METHOD_AVERAGE:
        $tooltip_text = $this->t('This value is the average of all monitoring periods values', [], $t_options);
        $tooltip_icon = 'moving';
        break;

      case self::CALCULATION_METHOD_MAXIMUM:
        $tooltip_text = $this->t('This value is the maximum of all monitoring periods values', [], $t_options);
        $tooltip_icon = 'equalizer';
        break;

      case self::CALCULATION_METHOD_LATEST:
        $tooltip_text = $this->t('This is the latest monitoring period value', [], $t_options);
        $tooltip_icon = 'watch_later';
        break;
    }
    if (!$tooltip_text) {
      return NULL;
    }
    $tooltip = [
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $tooltip_text . $reporting_period_text,
      '#class' => 'api-calculated',
      '#tag_content' => [
        '#theme' => 'hpc_icon',
        '#icon' => $tooltip_icon,
        '#tag' => 'span',
      ],
    ];
    return $tooltip;
  }

  /**
   * See if the attachment value uses a calculation method from the API.
   *
   * @param int $index
   *   The data point index.
   * @param array $data_point_conf
   *   Array with configuration for the specific data point to show.
   *
   * @return bool
   *   TRUE if a calculation method from the API is used, FALSE otherwise.
   */
  private function isApiCalculated($index, $data_point_conf) {
    if (array_key_exists('use_calculation_method', $data_point_conf) && $data_point_conf['use_calculation_method'] == FALSE) {
      return FALSE;
    }
    $calculation_method = $this->getCalculationMethod();
    return $this->isMeasurementIndex($index) && $calculation_method && $this->isValidCalculatedMethod($calculation_method);
  }

  /**
   * Check if the given calculation method is valid.
   *
   * @param string $calculation_method
   *   The calculation method to check.
   *
   * @return bool
   *   TRUE if the calculation method is valid, FALSE otherwise.
   */
  private function isValidCalculatedMethod($calculation_method) {
    return in_array(strtolower($calculation_method), [
      self::CALCULATION_METHOD_SUM,
      self::CALCULATION_METHOD_AVERAGE,
      self::CALCULATION_METHOD_MAXIMUM,
      self::CALCULATION_METHOD_LATEST,
    ]);
  }

}
