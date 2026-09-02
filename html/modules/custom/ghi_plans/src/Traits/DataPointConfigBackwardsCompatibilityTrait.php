<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;

/**
 * Trait with BC functionality.
 *
 * Make existing configuration compatible with the new fabric backend.
 */
trait DataPointConfigBackwardsCompatibilityTrait {

  /**
   * Update the given data point configuration.
   *
   * @param array $conf
   *   A data point config array.
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype
   *   An attachment prototype object.
   */
  public static function updateDataPointConfiguration(&$conf, AttachmentPrototype $prototype) {
    if (!empty($conf['data_points'][0]) && !array_key_exists('metric_type', $conf['data_points'][0])) {
      $conf['data_points'][0]['metric_type'] = self::getMetricTypeByIndex($conf['data_points'][0]['index'], $prototype);
    }
    if (!empty($conf['data_points'][1]) && !array_key_exists('metric_type', $conf['data_points'][1])) {
      $conf['data_points'][1]['metric_type'] = self::getMetricTypeByIndex($conf['data_points'][1]['index'], $prototype);
    }
  }

  /**
   * Get the metric type for the given index.
   *
   * @param int $index
   *   The index in the full list of fields.
   * @param \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype
   *   The index in the full list of fields.
   *
   * @return string|null
   *   The metric type or NULL.
   */
  public static function getMetricTypeByIndex(int $index, AttachmentPrototype $prototype): ?string {
    return $prototype->getMetricTypeByOriginalIndex($index) ?? $prototype->getFieldTypes()[$index] ?? NULL;
  }

}
