<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Helper class for retrieving and formatting data points.
 */
class DataPointHelper {

  /**
   * Get a value for a data point.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   */
  public static function getValue(DataAttachment $attachment, array $data_point_conf) {
    switch ($data_point_conf['processing']) {
      case 'single':
        return self::getSingleValue($attachment, $data_point_conf);

      case 'calculated':
        return self::getCalculatedValue($attachment, $data_point_conf);

      default:
        throw new InvalidTypeException(sprintf('Unknown processing type: %s', $data_point_conf['processing']));
    }
  }

  /**
   * Get a formatted value for a data point.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   */
  public static function formatValue(DataAttachment $attachment, array $data_point_conf) {
    $build = [];
    if (empty($data_point_conf['widget']) || $data_point_conf['widget'] == 'none') {
      $build = self::formatAsText($attachment, $data_point_conf);
    }
    else {
      $build = self::formatAsWidget($attachment, $data_point_conf);
    }
    if (self::isMeasurement($attachment, $data_point_conf) && $monitoring_period = self::formatMonitoringPeriod($attachment, 'icon')) {
      $build = [
        '#type' => 'container',
        0 => $build,
        1 => $monitoring_period,
      ];
    }
    return $build;
  }

  /**
   * Check if the given data point configuration involves measurement fields.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return bool
   *   TRUE if any of the involved data points is a measurement, FALSE
   *   otherwise.
   */
  private static function isMeasurement(DataAttachment $attachment, array $data_point_conf) {
    switch ($data_point_conf['processing']) {
      case 'single':
        $field = $attachment->fields[$data_point_conf['data_points'][0]];
        return $attachment->isMeasurementField($field);

      case 'calculated':
        $field_1 = $attachment->fields[$data_point_conf['data_points'][0]];
        $field_2 = $attachment->fields[$data_point_conf['data_points'][1]];
        return $attachment->isMeasurementField($field_1) || $attachment->isMeasurementField($field_2);

    }
    return FALSE;
  }

  /**
   * Get a formatted text value for a data point.
   *
   * @param object $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   */
  private static function formatAsText($attachment, array $data_point_conf) {
    $value = self::getValue($attachment, $data_point_conf);
    if ($value === NULL && $data_point_conf['formatting'] != 'percent') {
      return [
        '#markup' => t('Pending'),
      ];
    }
    $decimal_format = $data_point_conf['decimal_format'] ?? NULL;
    $rendered_value = NULL;
    switch ($data_point_conf['formatting']) {
      case 'raw':
        return [
          '#markup' => $value,
        ];

      case 'auto':
        if ($data_point_conf['processing'] == 'calculated' && $data_point_conf['formatting'] == 'percent') {
          $rendered_value = [
            '#theme' => 'hpc_percent',
            '#ratio' => $value,
            '#decimals' => 1,
            '#decimal_format' => $decimal_format,
          ];
        }
        $rendered_value = [
          '#theme' => 'hpc_autoformat_value',
          '#value' => $value,
          '#unit_type' => $attachment->unit ? $attachment->unit->type : 'amount',
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'currency':
        $rendered_value = [
          '#theme' => 'hpc_currency',
          '#value' => $value,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount_rounded':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'percent':
        $rendered_value = [
          '#theme' => 'hpc_percent',
          '#ratio' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;

      default:
        throw new InvalidTypeException(sprintf('Unknown formatting type: %s', $data_point_conf['formatting']));
    }

    return $rendered_value;
  }

  /**
   * Get a formatted widget for a data point.
   *
   * @param object $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Symfony\Component\Config\Definition\Exception\InvalidTypeException
   */
  private static function formatAsWidget($attachment, array $data_point_conf) {
    $value = self::getValue($attachment, $data_point_conf);
    switch ($data_point_conf['widget']) {
      case 'progressbar':
        $widget = [
          '#theme' => 'hpc_progress_bar',
          '#ratio' => $value,
        ];
        break;

      case 'pie_chart':
        $widget = [
          '#theme' => 'hpc_pie_chart',
          '#ratio' => $value,
        ];
        break;

      default:
        throw new InvalidTypeException(sprintf('Unknown widget type: %s', $data_point_conf['widget']));
    }

    return $widget;
  }

  /**
   * Get a formatted monitoring period for the attachment object.
   *
   * @param object $attachment
   *   The attachment object.
   * @param string $display_type
   *   The display type, either "icon" or "text".
   *
   * @return array|null
   *   A build array or NULL.
   */
  public static function formatMonitoringPeriod($attachment, $display_type) {
    if (!$attachment->monitoring_period) {
      return NULL;
    }
    switch ($display_type) {
      case 'icon':
        $build = [
          '#theme' => 'hpc_tooltip',
          '#tooltip' => ThemeHelper::render([
            '#theme' => 'hpc_reporting_period',
            '#reporting_period' => $attachment->monitoring_period,
          ], FALSE),
          '#class' => 'monitoring period',
          '#tag_content' => [
            '#theme' => 'hpc_icon',
            '#icon' => 'calendar_today',
            '#tag' => 'span',
          ],
        ];
        break;

      case 'text':
        $build = [
          '#theme' => 'hpc_reporting_period',
          '#reporting_period' => $attachment->monitoring_period,
        ];
        break;
    }
    return $build;
  }

  /**
   * Get a single value for a data point.
   *
   * @param object $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   */
  private static function getSingleValue($attachment, array $data_point_conf) {
    $value = $attachment->values[$data_point_conf['data_points'][0]];
    return $value;
  }

  /**
   * Get the calculated value for a data point.
   *
   * @param object $attachment
   *   The attachment object.
   * @param array $data_point_conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   */
  private static function getCalculatedValue($attachment, array $data_point_conf) {
    $value_1 = $attachment->values[$data_point_conf['data_points'][0]];
    $value_2 = $attachment->values[$data_point_conf['data_points'][1]];

    switch ($data_point_conf['calculation']) {
      case 'addition':
        $final_value = $value_1 + $value_2;
        break;

      case 'substraction':
        $final_value = $value_1 - $value_2;
        break;

      case 'division':
        $final_value = $value_1 != 0 ? $value_2 / $value_1 : NULL;
        break;

      case 'percentage':
        $final_value = $value_2 != 0 ? 1 / $value_2 * $value_1 : NULL;
        break;

      default:
        throw new InvalidTypeException(sprintf('Unknown calculation type: %s', $data_point_conf['calculation']));
    }

    return $final_value;
  }

  /**
   * Get an array of processing options.
   *
   * @return array
   *   The options array.
   */
  public static function getProcessingOptions() {
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
  public static function getCalculationOptions() {
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
  public static function getFormattingOptions() {
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
  public static function getWidgetOptions() {
    return [
      'none' => t('None'),
      'progressbar' => t('Progress bar'),
      'pie_chart' => t('Pie chart'),
      'spark_line' => t('Spark line'),
    ];
  }

}
