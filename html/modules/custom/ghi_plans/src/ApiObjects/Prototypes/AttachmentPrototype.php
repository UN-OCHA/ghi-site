<?php

namespace Drupal\ghi_plans\ApiObjects\Prototypes;

use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Helpers\StringHelper;

/**
 * Abstraction for API attachment prototype objects.
 */
class AttachmentPrototype extends ApiObjectBase {

  use PlanQueryTrait;

  /**
   * The plan id.
   *
   * @var string
   */
  protected string $planId;

  /**
   * The name.
   *
   * @var string|null
   */
  protected ?string $name;

  /**
   * The ref code.
   *
   * @var string
   */
  protected string $refCode;

  /**
   * The type.
   *
   * @var string
   */
  protected string $type;

  /**
   * The fields.
   *
   * @var array
   */
  protected array $fields;

  /**
   * The entity ref codes.
   *
   * @var array
   */
  protected array $entityRefCodes;

  /**
   * The metric fields.
   *
   * @var array
   */
  protected array $metricFields;

  /**
   * The measurement fields.
   *
   * @var array
   */
  protected array $measurementFields;

  /**
   * The calculated fields.
   *
   * @var array
   */
  protected array $calculatedFields;

  /**
   * The original fields.
   *
   * @var array
   */
  protected array $originalFields;

  /**
   * The field definitions keyed by their original legacy position.
   *
   * @var array
   */
  protected array $fieldDefinitions;

  /**
   * The calculation methods.
   *
   * @var array
   */
  protected array $calculationMethods;

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

  const FIELD_GROUP_PLANNING = 'planning';

  const FIELD_GROUP_MEASUREMENT = 'measurement';

  /**
   * Cache tag for attachment prototype field overrides.
   */
  public const FIELD_OVERRIDES_CACHE_TAG = 'ghi_plans:attachment_prototype_field_overrides';

  const INTERNAL_FIELD_QUALIFIERS = [
    'periodical' => 'periodical',
    'cumulative' => 'cumulative',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
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

    $this->planId = $data->PlanId;
    $this->name = $value->name->en ?? $value->Name ?? NULL;
    $this->refCode = $data->RefCode;
    $this->type = strtolower($data->Type);
    $this->entityRefCodes = $value->entities ?? [];
    $this->originalFields = $fields;
    $this->fieldDefinitions = $this->mapPrototypeFieldDefinitions($fields);
    $metric_field_count = count($metric_fields);
    $measurement_field_count = count($measurement_fields);
    $this->fields = $this->mapPrototypeFields($this->fieldDefinitions);
    $this->metricFields = $this->mapPrototypeFields(array_slice($this->fieldDefinitions, 0, $metric_field_count));
    $this->measurementFields = $this->mapPrototypeFields(array_slice($this->fieldDefinitions, $metric_field_count, $measurement_field_count));
    $this->calculatedFields = $this->mapPrototypeFields(array_slice($this->fieldDefinitions, $metric_field_count + $measurement_field_count));
    $this->calculationMethods = array_map(function ($item) {
      return strtolower($item);
    }, $value->calculationMethod ?? []);
    $this->setCacheTags([self::FIELD_OVERRIDES_CACHE_TAG]);
  }

  /**
   * Map the given fields to a simple type -> label list.
   *
   * @param array $definitions
   *   Resolved field definitions.
   *
   * @return string[]
   *   An array of strings, key being the types, values the labels.
   */
  private function mapPrototypeFields(array $definitions): array {
    $types = array_column($definitions, 'metric_type');
    $labels = array_column($definitions, 'label');

    return array_combine($types, $labels);
  }

  /**
   * Map fields to definitions that preserve the original field positions.
   *
   * @param array $fields
   *   An array of field objects as given in the raw prototype data.
   *
   * @return array
   *   Field definitions keyed by their original position.
   */
  private function mapPrototypeFieldDefinitions(array $fields): array {
    $types = $this->resolvePrototypeFieldTypes($fields);
    $definitions = [];

    foreach ($fields as $index => $field) {
      $source = $field->source ?? NULL;
      $definitions[$index] = [
        'index' => $index,
        'label' => $field->name->en ?? NULL,
        'metric_type' => $types[$index] ?? NULL,
        'raw_type' => $field->type ?? NULL,
        'source' => $source ? StringHelper::camelCaseToUnderscoreCase($source) : NULL,
        'raw_source' => $source,
      ];
    }

    return $definitions;
  }

  /**
   * Resolve raw prototype field types to local metric type machine names.
   *
   * @param array $fields
   *   An array of field objects as given in the raw prototype data.
   *
   * @return string[]
   *   Resolved metric type machine names keyed by original position.
   */
  private function resolvePrototypeFieldTypes(array $fields): array {
    $types = array_map(function ($item) {
      return StringHelper::camelCaseToUnderscoreCase($item->type);
    }, $fields ?? []);
    $labels = array_map(function ($item) {
      return $item->name->en;
    }, $fields);

    $original_types = $types;
    $metric_types = NULL;
    foreach ($original_types as $key => $type) {
      if (count(array_intersect($original_types, [$type])) > 1) {
        // There is uncertainty here, so we match for the label. The uncertainty
        // comes from older attachments that have duplicated metric types,
        // example attachment 38036, with 2 measure metrics of type "measure".
        $metric_types = $metric_types ?? $this->getEntityTypeQuery()?->getMetricTypes() ?? [];
        if ($metric_type = $this->getMatchingMetricType($labels[$key], $metric_types)) {
          $types[$key] = $metric_type->getMachineName();
        }
      }
    }
    return $types;
  }

  /**
   * Get the matching metric type for the given label.
   *
   * @param string $label
   *   The label to match.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType[] $metric_types
   *   The available metric types.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType|null
   *   The matching metric type, if found.
   */
  private function getMatchingMetricType(string $label, array $metric_types): ?MetricType {
    foreach ([TRUE, FALSE] as $case_sensitive) {
      foreach ($metric_types as $metric_type) {
        if ($metric_type->matches($label, $case_sensitive)) {
          return $metric_type;
        }
      }
    }
    return NULL;
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
   * Get the plan id of the attachment prototype.
   *
   * @return int|null
   *   The plan id of the attachment prototype.
   */
  public function getPlanId(): ?int {
    return $this->planId ?? NULL;
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
   *   An array of field labels, keyed by metric type.
   */
  public function getFields() {
    return $this->fields;
  }

  /**
   * Get the available field types for this prototype.
   *
   * @return string[]
   *   An indexed array of canonical metric types.
   */
  public function getFieldTypes() {
    return array_keys($this->fields);
  }

  /**
   * Add a field definition inferred from actual attachment fact data.
   *
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to add.
   * @param string $field_group
   *   The prototype field group to add to.
   * @param string|null $label
   *   An optional field label.
   */
  public function addMissingMetricTypeField(MetricType $metric_type, string $field_group, ?string $label = NULL): void {
    $metric_type_name = $metric_type->getMachineName();
    if (array_key_exists($metric_type_name, $this->fields)) {
      $this->addMetricTypeToFieldGroup($metric_type_name, $label ?? $this->fields[$metric_type_name], $field_group);
      return;
    }

    $label = $label ?? $this->getDefaultInternalFieldLabel($metric_type, $field_group);
    $raw_type = $metric_type->getRawData()?->HPCType ?? NULL;
    $index = empty($this->fieldDefinitions) ? 0 : max(array_map('intval', array_keys($this->fieldDefinitions))) + 1;

    $this->fields[$metric_type_name] = $label;
    $this->addMetricTypeToFieldGroup($metric_type_name, $label, $field_group);
    $this->fieldDefinitions[$index] = [
      'index' => $index,
      'label' => $label,
      'metric_type' => $metric_type_name,
      'raw_type' => $raw_type,
      'source' => NULL,
      'raw_source' => NULL,
    ];
  }

  /**
   * Replace an original prototype field with a corrected metric type.
   *
   * @param int $index
   *   The original field index to replace.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to use for the original field index.
   * @param string $field_group
   *   The prototype field group to put the corrected field in.
   * @param string|null $label
   *   An optional replacement field label.
   */
  public function replaceMetricTypeField(int $index, MetricType $metric_type, string $field_group, ?string $label = NULL): void {
    if (!array_key_exists($index, $this->fieldDefinitions)) {
      throw new \InvalidArgumentException(sprintf('Attachment prototype field index %d does not exist.', $index));
    }

    $definition = $this->fieldDefinitions[$index];
    $old_metric_type = $definition['metric_type'] ?? NULL;
    $metric_type_name = $metric_type->getMachineName();
    $label = $label ?? $definition['label'] ?? $this->getDefaultInternalFieldLabel($metric_type, $field_group);

    $this->fieldDefinitions[$index] = $definition + [
      'index' => $index,
      'source' => NULL,
      'raw_source' => NULL,
    ];
    $this->fieldDefinitions[$index]['label'] = $label;
    $this->fieldDefinitions[$index]['metric_type'] = $metric_type_name;
    $this->fieldDefinitions[$index]['raw_type'] = $metric_type->getRawData()?->HPCType ?? $definition['raw_type'] ?? NULL;

    $remove_old_metric_type = $old_metric_type && !$this->fieldDefinitionUsesMetricType($old_metric_type);
    $this->replaceMetricTypeInFieldCollections($old_metric_type, $metric_type_name, $label, $field_group, $remove_old_metric_type);
  }

  /**
   * Add an existing metric type to the appropriate field group.
   *
   * @param string $metric_type
   *   The metric type machine name.
   * @param string $label
   *   The field label.
   * @param string $field_group
   *   The field group to add to.
   */
  private function addMetricTypeToFieldGroup(string $metric_type, string $label, string $field_group): void {
    switch ($field_group) {
      case self::FIELD_GROUP_PLANNING:
        $this->metricFields[$metric_type] = $label;
        return;

      case self::FIELD_GROUP_MEASUREMENT:
        $this->measurementFields[$metric_type] = $label;
        return;
    }

    throw new \InvalidArgumentException(sprintf('Unsupported attachment prototype field group %s.', $field_group));
  }

  /**
   * Replace a metric type key in all field collections.
   *
   * @param string|null $old_metric_type
   *   The old metric type machine name.
   * @param string $metric_type
   *   The new metric type machine name.
   * @param string $label
   *   The field label.
   * @param string $field_group
   *   The field group to add the new metric type to.
   * @param bool $remove_old_metric_type
   *   Whether the old metric type should be removed from all collections.
   */
  private function replaceMetricTypeInFieldCollections(?string $old_metric_type, string $metric_type, string $label, string $field_group, bool $remove_old_metric_type): void {
    if ($remove_old_metric_type) {
      unset($this->fields[$old_metric_type], $this->metricFields[$old_metric_type], $this->measurementFields[$old_metric_type], $this->calculatedFields[$old_metric_type]);
    }

    $replace_metric_type = $old_metric_type === $metric_type || $remove_old_metric_type ? $old_metric_type : NULL;
    $this->fields = $this->replaceMetricTypeKey($this->fields, $replace_metric_type, $metric_type, $label);
    switch ($field_group) {
      case self::FIELD_GROUP_PLANNING:
        $this->metricFields = $this->replaceMetricTypeKey($this->metricFields, $replace_metric_type, $metric_type, $label);
        unset($this->measurementFields[$metric_type], $this->calculatedFields[$metric_type]);
        return;

      case self::FIELD_GROUP_MEASUREMENT:
        $this->measurementFields = $this->replaceMetricTypeKey($this->measurementFields, $replace_metric_type, $metric_type, $label);
        unset($this->metricFields[$metric_type], $this->calculatedFields[$metric_type]);
        return;
    }

    throw new \InvalidArgumentException(sprintf('Unsupported attachment prototype field group %s.', $field_group));
  }

  /**
   * Replace a metric type key in a keyed field-label list.
   *
   * @param string[] $fields
   *   The keyed field list.
   * @param string|null $old_metric_type
   *   The old metric type machine name.
   * @param string $metric_type
   *   The new metric type machine name.
   * @param string $label
   *   The field label.
   *
   * @return string[]
   *   The updated keyed field list.
   */
  private function replaceMetricTypeKey(array $fields, ?string $old_metric_type, string $metric_type, string $label): array {
    if ($old_metric_type === $metric_type && array_key_exists($metric_type, $fields)) {
      $fields[$metric_type] = $label;
      return $fields;
    }

    $updated_fields = [];
    $replaced = FALSE;
    foreach ($fields as $field_metric_type => $field_label) {
      if ($field_metric_type === $metric_type) {
        continue;
      }
      if ($old_metric_type !== NULL && $field_metric_type === $old_metric_type) {
        $updated_fields[$metric_type] = $label;
        $replaced = TRUE;
        continue;
      }
      $updated_fields[$field_metric_type] = $field_label;
    }

    if (!$replaced) {
      $updated_fields[$metric_type] = $label;
    }
    return $updated_fields;
  }

  /**
   * Check whether any field definition uses the given metric type.
   *
   * @param string $metric_type
   *   The metric type machine name.
   *
   * @return bool
   *   TRUE if the metric type is still used, FALSE otherwise.
   */
  private function fieldDefinitionUsesMetricType(string $metric_type): bool {
    foreach ($this->fieldDefinitions as $definition) {
      if (($definition['metric_type'] ?? NULL) === $metric_type) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get a label for a field inferred from actual attachment fact data.
   *
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type to label.
   * @param string $field_group
   *   The prototype field group being added to.
   *
   * @return string
   *   The inferred field label.
   */
  private function getDefaultInternalFieldLabel(MetricType $metric_type, string $field_group): string {
    $metric_type_name = $metric_type->getMachineName();
    return $this->deriveInternalFieldLabelFromSibling($metric_type_name, $field_group) ?? $metric_type->getLabel();
  }

  /**
   * Derive a label for a missing metric type from a related prototype field.
   *
   * @param string $metric_type
   *   The missing metric type machine name.
   * @param string $field_group
   *   The prototype field group being added to.
   *
   * @return string|null
   *   The derived field label, if one can be built.
   */
  private function deriveInternalFieldLabelFromSibling(string $metric_type, string $field_group): ?string {
    $parsed_metric_type = $this->parseQualifiedMetricType($metric_type);
    if (!$parsed_metric_type) {
      return NULL;
    }

    [$qualifier, $base_metric_type] = $parsed_metric_type;
    $fields = $this->getFieldsForFieldGroup($field_group);

    foreach (array_keys(self::INTERNAL_FIELD_QUALIFIERS) as $sibling_qualifier) {
      if ($sibling_qualifier === $qualifier) {
        continue;
      }

      $sibling_metric_type = $sibling_qualifier . '_' . $base_metric_type;
      if (!array_key_exists($sibling_metric_type, $fields)) {
        continue;
      }

      $label = $this->replaceInternalFieldLabelQualifier($fields[$sibling_metric_type], self::INTERNAL_FIELD_QUALIFIERS[$qualifier]);
      if ($label) {
        return $label;
      }
    }

    return NULL;
  }

  /**
   * Parse the qualifier and base metric type from a metric type machine name.
   *
   * @param string $metric_type
   *   The metric type machine name.
   *
   * @return array|null
   *   The qualifier and base metric type, or NULL if there is no qualifier.
   */
  private function parseQualifiedMetricType(string $metric_type): ?array {
    foreach (array_keys(self::INTERNAL_FIELD_QUALIFIERS) as $qualifier) {
      $prefix = $qualifier . '_';
      if (str_starts_with($metric_type, $prefix)) {
        return [$qualifier, substr($metric_type, strlen($prefix))];
      }
    }
    return NULL;
  }

  /**
   * Replace the trailing parenthetical field label qualifier.
   *
   * @param string $label
   *   The existing field label.
   * @param string $qualifier
   *   The replacement qualifier.
   *
   * @return string|null
   *   The label with the replacement qualifier, if possible.
   */
  private function replaceInternalFieldLabelQualifier(string $label, string $qualifier): ?string {
    if (!preg_match('/^(.*?)\s*\([^()]*\)\s*$/', $label, $matches)) {
      return NULL;
    }
    return trim($matches[1]) . ' (' . $qualifier . ')';
  }

  /**
   * Get the fields for a prototype field group.
   *
   * @param string $field_group
   *   The field group.
   *
   * @return string[]
   *   The fields for the group.
   */
  private function getFieldsForFieldGroup(string $field_group): array {
    switch ($field_group) {
      case self::FIELD_GROUP_PLANNING:
        return $this->metricFields;

      case self::FIELD_GROUP_MEASUREMENT:
        return $this->measurementFields;
    }

    throw new \InvalidArgumentException(sprintf('Unsupported attachment prototype field group %s.', $field_group));
  }

  /**
   * Get the field definitions keyed by their original legacy position.
   *
   * @return array
   *   Field definitions keyed by their original position.
   */
  public function getFieldDefinitions(): array {
    return $this->fieldDefinitions;
  }

  /**
   * Get the field definition for the given original index.
   *
   * @param int|string $index
   *   The original field index.
   *
   * @return array|null
   *   The field definition, if found.
   */
  public function getFieldDefinitionByOriginalIndex($index): ?array {
    return $this->fieldDefinitions[$index] ?? NULL;
  }

  /**
   * Get the field definition for the given metric type.
   *
   * @param string $metric_type
   *   The metric type.
   *
   * @return array|null
   *   The field definition, if found.
   */
  public function getFieldDefinitionByMetricType(string $metric_type): ?array {
    foreach ($this->fieldDefinitions as $definition) {
      if (($definition['metric_type'] ?? NULL) == $metric_type) {
        return $definition;
      }
    }
    return NULL;
  }

  /**
   * Get the metric type for a legacy field index.
   *
   * @param int $index
   *   The original field index.
   *
   * @return string|null
   *   The metric type, if found.
   */
  public function getMetricTypeByOriginalIndex(int $index): ?string {
    $definition = $this->getFieldDefinitionByOriginalIndex($index);
    return $definition['metric_type'] ?? NULL;
  }

  /**
   * Get the original field index for a metric type.
   *
   * @param string $metric_type
   *   The metric type.
   *
   * @return int|null
   *   The original field index, if found.
   */
  public function getOriginalIndexByMetricType(string $metric_type): ?int {
    $definition = $this->getFieldDefinitionByMetricType($metric_type);
    return $definition['index'] ?? NULL;
  }

  /**
   * Get the original field items from the API.
   *
   * @return array
   *   An array of field items.
   */
  public function getOriginalFields() {
    return $this->originalFields;
  }

  /**
   * Get the fields that represent planning metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getPlanningFields() {
    return $this->metricFields;
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementFields() {
    return $this->measurementFields;
  }

  /**
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedFields() {
    return $this->calculatedFields;
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
      case 'custom_measure':
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
    return $this->calculationMethods;
  }

  /**
   * The prototype ref code, e.g. BP, BF, ...
   *
   * @return string
   *   The ref code string.
   */
  public function getRefCode() {
    return $this->refCode;
  }

  /**
   * The entity type ref codes of entities using attachments of this type.
   *
   * @return string[]
   *   An array of strings, e.g. SO, CQ, HC, ...
   */
  public function getEntityRefCodes() {
    return $this->entityRefCodes ?? [];
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
