<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

/**
 * Abstraction for API data attachment objects.
 */
class IndicatorAttachment extends DataAttachment {

  /**
   * Define calculation methods.
   */
  const CALCULATION_METHOD_SUM = 'sum';
  const CALCULATION_METHOD_AVERAGE = 'average';
  const CALCULATION_METHOD_MAXIMUM = 'maximum value';
  const CALCULATION_METHOD_LATEST = 'latest value';
  const CALCULATION_METHOD_MANUAL = 'manual value';

  /**
   * {@inheritdoc}
   */
  protected function getSingleValue($index) {
    $calculation_method = $this->getCalculationMethod();
    if (!$this->isApiCalculated($index)) {
      return $this->getValueForDataPoint($index);
    }
    $value = NULL;
    $values = $this->getValuesForAllReportingPeriods($index);
    switch (strtolower($calculation_method)) {
      case self::CALCULATION_METHOD_SUM:
        $value = array_sum($values);
        break;

      case self::CALCULATION_METHOD_AVERAGE:
        $values = array_filter($values);
        $value = array_sum($values) / count($values);
        break;

      case self::CALCULATION_METHOD_MAXIMUM:
        $value = max($values);
        break;

      case self::CALCULATION_METHOD_LATEST:
        $values = array_filter($values);
        $value = end($values);
        break;

      case self::CALCULATION_METHOD_MANUAL:
        // @todo Implement this.
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
    return in_array($calculation_method, $available_methods) ? $calculation_method : NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTooltip($conf) {
    $index = $conf['data_points'][0]['index'];
    if (empty($this->getSingleValue($index))) {
      return NULL;
    }
    if ($this->isApiCalculated($index) && $conf['processing'] != 'calculated') {
      $tooltip_icon = NULL;
      $tooltip_text = NULL;
      $calculation_method = $this->getCalculationMethod();
      switch (strtolower($calculation_method)) {
        case self::CALCULATION_METHOD_SUM:
          $tooltip_text = $this->t('This value is the sum of all monitoring periods values');
          $tooltip_icon = 'functions';
          break;

        case self::CALCULATION_METHOD_AVERAGE:
          $tooltip_text = $this->t('This value is the average of all monitoring periods values');
          $tooltip_icon = 'moving';
          break;

        case self::CALCULATION_METHOD_MAXIMUM:
          $tooltip_text = $this->t('This value is the maximum of all monitoring periods values');
          $tooltip_icon = 'equalizer';
          break;

        case self::CALCULATION_METHOD_LATEST:
          $tooltip_text = $this->t('This is the latest monitoring period value');
          $tooltip_icon = 'watch_later';
          break;

        case self::CALCULATION_METHOD_MANUAL:
          $tooltip_text = $this->t('This value is calculated of all monitoring periods values');
          $tooltip_icon = 'blood_pressure';
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
      return $this->formatMonitoringPeriod('icon', $conf);
    }
    return NULL;
  }

  /**
   * See if the attachment value uses a calculation method from the API.
   *
   * @param int $index
   *   The data point index.
   *
   * @return bool
   *   TRUE if a calculation method from the API is used, FALSE otherwise.
   */
  private function isApiCalculated($index) {
    $calculation_method = $this->getCalculationMethod();
    return $this->isMeasurementIndex($index) && $calculation_method;
  }

}
