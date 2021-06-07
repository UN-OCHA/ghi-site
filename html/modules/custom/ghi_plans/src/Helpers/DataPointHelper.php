<?php

namespace Drupal\ghi_plans\Helpers;

use Symfony\Component\Config\Definition\Exception\InvalidTypeException;

/**
 * Helper class for retrieving and formatting data points.
 */
class DataPointHelper {

  /**
   * Get a value for a data point.
   *
   * @param object $attachment
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
  public static function getValue($attachment, array $data_point_conf) {
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
  public static function formatValue($attachment, array $data_point_conf) {
    $value = self::getValue($attachment, $data_point_conf);
    switch ($data_point_conf['formatting']) {
      case 'raw':
        return $value;

      case 'auto':
        return [
          '#theme' => 'hpc_autoformat_value',
          '#value' => $value,
          '#unit_type' => $attachment->unit->type,
        ];

      case 'currency':
        return [
          '#theme' => 'hpc_currency',
          '#value' => $value,
        ];

      case 'amount':
        return [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
        ];

      case 'amount_rounded':
        return [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#decimals' => 1,
        ];

      case 'percent':
        return [
          '#theme' => 'hpc_percent',
          '#ratio' => $value,
          '#decimals' => 1,
        ];

      default:
        throw new InvalidTypeException(sprintf('Unknown formatting type: %s', $data_point_conf['formatting']));
    }
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
        $final_value = $value_2 != 0 ? 100 / $value_2 * $value_1 : NULL;
        break;

      default:
        throw new InvalidTypeException(sprintf('Unknown calculation type: %s', $data_point_conf['calculation']));
    }

    return $final_value;
  }

}
