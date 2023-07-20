<?php

namespace Drupal\ghi_plans\ApiObjects\AttachmentPrototype;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction for API attachment prototype objects.
 */
class AttachmentPrototype extends ApiObjectBase {

  const DATA_TYPES = [
    'indicator',
    'caseload',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $prototype = $this->getRawData();
    $metric_fields = array_map(function ($item) {
      return $item->name->en;
    }, $prototype->value->metrics ?? []);
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
      'entity_ref_codes' => $prototype->value->entities ?? [],
      'metric_fields' => $metric_fields,
      'measurement_fields' => $measurement_fields,
      'calculation_methods' => $prototype->value->calculationMethod ?? [],
    ];
  }

  /**
   * Get the name of the attachment prototype.
   *
   * @return string
   *   The name of the attachment prototype.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Get the type of the attachment prototype.
   *
   * @return string
   *   The type of the attachment prototype.
   */
  public function getType() {
    return strtolower($this->type);
  }

  /**
   * Get the type label of the attachment prototype.
   *
   * @return string
   *   The type label of the attachment prototype.
   */
  public function getTypeLabel() {
    return ucfirst(strtolower($this->type));
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
  public function getGoalMetricFields() {
    $fields = $this->metric_fields;
    return array_filter($this->fields, function ($field) use ($fields) {
      return in_array($field, $fields);
    });
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

  /**
   * Check if this attachment prototype represents an indicator.
   *
   * @return bool
   *   TRUE if the prototype represents an indicator, FALSE otherwise.
   */
  public function isIndicator() {
    return $this->type == 'indicator';
  }

  /**
   * Get the available calculation methods for measures in this prototype.
   *
   * @return array
   *   Array of calculation method labels.
   */
  public function getCalculationMethods() {
    return $this->calculation_methods;
  }

  /**
   * The entity type ref codes of entities using attachments of this type.
   *
   * @return string[]
   *   An array of strings, e.g. SO, CQ, HC, ...
   */
  public function getEntityRefCodes() {
    return $this->entity_ref_codes ?? [];
  }

  /**
   * Check if the attachment prototype represents a data attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype
   *   The attachment prototype to check.
   *
   * @return bool
   *   TRUE if the given attachment prototype represents a data attachment,
   *   FALSE otherwise.
   */
  public static function isDataType($attachment_prototype) {
    return in_array(strtolower($attachment_prototype->type), self::DATA_TYPES);
  }

}
