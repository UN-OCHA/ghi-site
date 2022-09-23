<?php

namespace Drupal\ghi_plans\ApiObjects\AttachmentPrototype;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction for API attachment prototype objects.
 */
class AttachmentPrototype extends ApiObjectBase {

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $prototype = $this->getRawData();
    $measurement_fields = array_map(function ($item) {
      return $item->name->en;
    }, $prototype->value->measureFields ?? []);
    return (object) [
      'id' => $prototype->id,
      'name' => $prototype->value->name->en,
      'ref_code' => $prototype->refCode,
      'type' => strtolower($prototype->type),
      'fields' => array_merge(
        array_map(function ($item) {
          return $item->name->en;
        }, $prototype->value->metrics),
        $measurement_fields
      ),
      'field_types' => array_merge(
        array_map(function ($item) {
          return StringHelper::camelCaseToUnderscoreCase($item->type);
        }, $prototype->value->metrics),
        array_map(function ($item) {
          return StringHelper::camelCaseToUnderscoreCase($item->type);
        }, $prototype->value->measureFields ?? [])
      ),
      'measurement_fields' => $measurement_fields,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementMetricFields() {
    $measurements = $this->measurement_fields;
    return array_filter($this->fields, function ($field) use ($measurements) {
      return in_array($field, $measurements);
    });
  }

}
