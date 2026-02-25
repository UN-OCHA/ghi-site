<?php

namespace Drupal\ghi_plans\ApiObjects\Prototypes;

use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction for API attachment prototype objects.
 */
class AttachmentPrototype extends ApiObjectBase {

  use PlanQueryTrait;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'RefCode',
    'Type',
    'Value',
    'PlanId',
    'CreatedAt',
    'UpdatedAt',
    'RecordStatus',
    'Source',
    'SourceId',
  ];

  const DATA_TYPES = [
    'indicator',
    'caseload',
  ];

  const LABEL_MAP = [
    'filewebcontent' => 'File (web content)',
    'cost' => 'Cost',
    'indicator' => 'Indicator',
    'caseload' => 'Caseload',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map(): object {
    $data = $this->getRawData();
    $value = is_string($data->Value) ? json_decode($data->Value ?? '') : $data->Value;
    $metric_fields = $value->metrics ?? [];
    $measurement_fields = $value->measureFields ?? [];
    $calculated_fields = $value->calculatedFields ?? [];
    if (count($calculated_fields) == 1 && is_array($calculated_fields[0])) {
      $calculated_fields = reset($calculated_fields);
    }
    $calculated_fields = array_filter($calculated_fields);

    $fields = array_merge(
      $metric_fields,
      $measurement_fields,
      $calculated_fields,
    );

    return (object) [
      'id' => $data->Id,
      'name' => $value->Name ?? NULL,
      'ref_code' => $data->RefCode,
      'type' => strtolower($data->Type),
      'fields' => $this->mapPrototypeFields($fields),
      'entity_ref_codes' => $value->entities ?? [],
      'metric_fields' => $this->mapPrototypeFields($metric_fields),
      'measurement_fields' => $this->mapPrototypeFields($measurement_fields),
      'calculated_fields' => $this->mapPrototypeFields($calculated_fields),
      'original_fields' => $fields,
      'calculation_methods' => array_map(function ($item) {
        return strtolower($item);
      }, $value->calculationMethod ?? []),
    ];
  }

  /**
   * Map the given fields to a simple type -> label list.
   *
   * @param array $fields
   *   An array of field objects as given in the raw prototype data.
   *
   * @return string[]
   *   An array of strings, key being the types, values the labels.
   */
  private function mapPrototypeFields(array $fields) {
    $types = array_map(function ($item) {
      return StringHelper::camelCaseToUnderscoreCase($item->type);
    }, $fields ?? []);
    $labels = array_map(function ($item) {
      return $item->name->en;
    }, $fields);

    foreach ($types as $key => $type) {
      if (count(array_intersect($types, [$type])) > 1) {
        // There is uncertainty here, so we match for the label. The uncertainty
        // comes from older attachments that have duplicated metric types,
        // example attachment 38036, with 2 measure metrics of type "measure".
        foreach ($this->getEntityTypeQuery()?->getMetricTypes() ?? [] as $metric_type) {
          if (!$metric_type->matches($labels[$key])) {
            continue;
          }
          $types[$key] = $metric_type->getMachineName();
        }
      }
    }
    return array_combine($types, $labels);
  }

  /**
   * Get the name of the attachment prototype.
   *
   * @return string
   *   The name of the attachment prototype.
   */
  public function getName(): string {
    return $this->name ?? $this->getTypeLabel();
  }

  /**
   * Get the type of the attachment prototype.
   *
   * @return string
   *   The type of the attachment prototype.
   */
  public function getType(): string {
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
    return array_keys($this->fields);
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
   * Get the fields that represent planning metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getPlanningFields() {
    return $this->metric_fields;
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementFields() {
    return $this->measurement_fields;
  }

  /**
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedFields() {
    return $this->calculated_fields;
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
   * @param string $metric_type
   *   The metric type of the field in the prototype.
   * @param string|null $langcode
   *   A language code.
   *
   * @return string|null
   *   The (translated) field label or NULL.
   */
  public function getDefaultFieldLabel(string $metric_type, $langcode = NULL) {
    // This is the place for special handling of some types.
    switch ($metric_type) {
      case 'cumulative_reach':
        return (string) $this->t('People reached', [], ['langcode' => $langcode]);

      case 'periodical_measure':
      case 'cumulative_measure':
      case 'measure':
        return (string) $this->t('Measure', [], ['langcode' => $langcode]);
    }
    $fields = $this->getFields();
    return $fields[$metric_type] ?? NULL;
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
    return in_array(strtolower($attachment_prototype->Type), self::DATA_TYPES);
  }

}
