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
  public function getSingleValue($index, array $reporting_periods = NULL, $data_point_conf = []) {
    if (!$this->isApiCalculated($index, $data_point_conf)) {
      $monitoring_period = !empty($reporting_periods) ? array_key_last($reporting_periods) : 'latest';
      return $this->getValueForDataPoint($index, $monitoring_period);
    }
    $value = NULL;
    $values = $this->getValuesForAllReportingPeriods($index, TRUE, $reporting_periods);
    if (empty($values)) {
      return $value;
    }
    $calculation_method = $this->getCalculationMethod();
    switch (strtolower($calculation_method)) {
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
    $index = $conf['data_points'][0]['index'];
    if (empty($this->getSingleValue($index, NULL, $conf['data_points'][0]))) {
      return NULL;
    }
    $last_reporting_period = $this->getLastNonEmptyReportingPeriod($index);
    if (!$last_reporting_period) {
      return NULL;
    }
    if ($this->isApiCalculated($index, $conf['data_points'][0]) && $conf['processing'] != 'calculated') {
      $tooltip_icon = NULL;
      $tooltip_text = NULL;
      $reporting_period_text = ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $last_reporting_period,
        '#format_string' => ', as of date @end_date',
      ], FALSE);
      $calculation_method = $this->getCalculationMethod();
      switch (strtolower($calculation_method)) {
        case self::CALCULATION_METHOD_SUM:
          $tooltip_text = $this->t('This value is the sum of all monitoring periods values') . $reporting_period_text;
          $tooltip_icon = 'functions';
          break;

        case self::CALCULATION_METHOD_AVERAGE:
          $tooltip_text = $this->t('This value is the average of all monitoring periods values') . $reporting_period_text;
          $tooltip_icon = 'moving';
          break;

        case self::CALCULATION_METHOD_MAXIMUM:
          $tooltip_text = $this->t('This value is the maximum of all monitoring periods values') . $reporting_period_text;
          $tooltip_icon = 'equalizer';
          break;

        case self::CALCULATION_METHOD_LATEST:
          $tooltip_text = $this->t('This is the latest monitoring period value') . $reporting_period_text;
          $tooltip_icon = 'watch_later';
          break;
      }
      $tooltip = [
        '#theme' => 'hpc_tooltip',
        '#tooltip' => $tooltip_text,
        '#class' => 'api-calculated',
        '#tag_content' => [
          '#theme' => 'hpc_icon',
          '#icon' => $tooltip_icon,
          '#tag' => 'span',
        ],
      ];
      return $tooltip;
    }
    elseif ($this->isMeasurement($conf)) {
      // Otherwise see if this is a measurement and if we can get a formatted
      // monitoring period for this data point.
      return $this->formatMonitoringPeriod('icon', $last_reporting_period->id, 'as of date @end_date');
    }
    return NULL;
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
