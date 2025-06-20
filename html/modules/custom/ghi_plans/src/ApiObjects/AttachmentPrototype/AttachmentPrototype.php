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

  const LABEL_MAP = [
    'textwebcontent' => 'Text (web content)',
    'filewebcontent' => 'File (web content)',
    'contact' => 'Contact',
    'cost' => 'Cost',
    'indicator' => 'Indicator',
    'caseload' => 'Caseload',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $prototype = $this->getRawData();
    $metric_fields = $prototype->value->metrics ?? [];
    $measurement_fields = $prototype->value->measureFields ?? [];
    $calculated_fields = $prototype->value->calculatedFields ?? [];
    if (count($calculated_fields) == 1 && is_array($calculated_fields[0])) {
      $calculated_fields = reset($calculated_fields);
    }
    $calculated_fields = array_filter($calculated_fields);
    $all_fields = array_merge(
      $metric_fields,
      $measurement_fields,
      $calculated_fields,
    );
    return (object) [
      'id' => $prototype->id,
      'name' => $prototype->value->name->en,
      'ref_code' => $prototype->refCode,
      'type' => strtolower($prototype->type),
      'fields' => array_map(function ($item) {
        return $item->name->en;
      }, $all_fields),
      'field_types' => array_map(function ($item) {
        return StringHelper::camelCaseToUnderscoreCase($item->type);
      }, $all_fields ?? []),
      'entity_ref_codes' => $prototype->value->entities ?? [],
      'metric_fields' => array_map(function ($item) {
        return $item->name->en;
      }, $metric_fields),
      'measurement_fields' => array_map(function ($item) {
        return $item->name->en;
      }, $measurement_fields),
      'calculated_fields' => array_map(function ($item) {
        return $item->name->en;
      }, $calculated_fields),
      'original_fields' => $all_fields,
      'calculation_methods' => array_map(function ($item) {
        return strtolower($item);
      }, $prototype->value->calculationMethod ?? []),
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
    return self::LABEL_MAP[$this->getType()] ?? ucfirst(strtolower($this->type));
  }

  /**
   * Get the available fields for this prototype.
   *
   * @return string[]
   *   An array of field labels, keyed by their index.
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Get the available field types for this prototype.
   *
   * @return string[]
   *   An array of field types, keyed by their index.
   */
  public function getFieldTypes() {
    return $this->field_types;
  }

  /**
   * Get the original field items from the API.
   *
   * @return array
   *   An array of field items.
   */
  public function getOriginalFields() {
    return $this->original_fields;
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
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedMetricFields() {
    $calculated_fields = $this->calculated_fields;
    return array_filter($this->fields, function ($field) use ($calculated_fields) {
      return in_array($field, $calculated_fields);
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
   * Get the default label for the field with the given index.
   *
   * @param int $index
   *   The index of the field in the prototype.
   * @param string|null $langcode
   *   A language code.
   *
   * @return string|null
   *   The (translated) field label or NULL.
   */
  public function getDefaultFieldLabel($index, $langcode = NULL) {
    $field_types = $this->getFieldTypes();
    if ($type = $field_types[$index]) {
      // This is the place for special handling of some types.
      switch ($type) {
        case 'cumulative_reach':
          return (string) $this->t('People reached', [], ['langcode' => $langcode]);

        case 'periodical_measure':
        case 'cumulative_measure':
        case 'measure':
          return (string) $this->t('Measure', [], ['langcode' => $langcode]);
      }
    }
    $fields = $this->getFields();
    return $fields[$index] ?? NULL;
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
   * The prototype ref code, e.g. BP, BF, ...
   *
   * @return string
   *   The ref code string.
   */
  public function getRefCode() {
    return $this->ref_code;
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
   * Check if the given raw attachment prototype represents a data attachment.
   *
   * @param object $attachment_prototype
   *   The attachment prototype raw data to check.
   *
   * @return bool
   *   TRUE if the given attachment prototype represents a data attachment,
   *   FALSE otherwise.
   */
  public static function isDataType($attachment_prototype) {
    return in_array(strtolower($attachment_prototype->type), self::DATA_TYPES);
  }

}
