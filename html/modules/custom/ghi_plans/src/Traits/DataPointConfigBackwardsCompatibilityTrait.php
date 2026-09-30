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
    foreach ($conf['data_points'] ?? [] as $data_point_index => $data_point) {
      if (empty($data_point) || array_key_exists('metric_type', $data_point)) {
        continue;
      }
      $metric_type = $prototype->resolveMetricType($data_point['index'] ?? NULL);
      if ($metric_type !== NULL) {
        $conf['data_points'][$data_point_index]['metric_type'] = $metric_type;
      }
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
    return $prototype->resolveMetricType($index);
  }

}
