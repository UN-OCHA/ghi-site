<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\Types\Unit;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_api\Traits\DateTimeTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Abstraction for API data attachment objects.
 */
class DataAttachment extends AttachmentBase implements DataAttachmentInterface {

  use DateTimeTrait;
  use DisaggregatedDataTrait;
  use PlanQueryTrait;
  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;
  use StringTranslationTrait;

  /**
   * The source entity of an attachment.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   */
  protected $sourceEntity;

  /**
   * The attachment prototype.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype
   */
  protected $prototype;

  /**
   * Define a list of field types that should be considered cumulative reach.
   */
  const CUMULATIVE_REACH_FIELDS = [
    'cumulativeReach',
    'optionNonPlanCumulReach',
    'optionOverallCumulReach',
  ];

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'PlanId',
    'EntityId',
    'EntityTypeId',
    'EntityMainType',
    'AttachmentType',
    'CustomReference',
    'HasDisaggregatedData',
    'UnitId',
    'CalculationMethodId',
    'Description',
    'VisibilityGroupId',
    'AttachmentPrototypeId',
    'RecordStatus',
    // 'ActiveUntil',
    // 'Source',
    // 'SourceId',
    // 'CreatedAt',
    'UpdatedAt',
    // 'IsLocked',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $query = $this->getEntityTypeQuery();

    $attachment = $this->getRawData();
    $period = $this->fetchReportingPeriodForAttachment();

    $processed = (object) [
      'id' => $attachment->Id,
      'type' => strtolower($attachment->AttachmentType),
      'source' => (object) [
        'entity_type' => PlanEntityHelper::checkObjectType($attachment->EntityMainType ?? NULL),
        'entity_id' => $attachment->EntityId ?? NULL,
        'plan_id' => $attachment->PlanId ?? NULL,
      ],
      'attachment_prototype_id' => $attachment->AttachmentPrototypeId,
      'custom_id' => $attachment->CustomReference ?? NULL,
      'composed_reference' => $attachment->ComposedReference ?? NULL,
      'description' => $attachment->Name ?? NULL,
      'values' => $this->extractValues(),
      'unit' => ($attachment->UnitId ?? NULL) ? $query->getUnit($attachment->UnitId) : NULL,
      'monitoring_period' => $period ?? NULL,
      'has_disaggregated_data' => !empty($attachment->HasDisaggregatedData),
      'calculation_method' => ($attachment->CalculationMethodId ?? NULL) ? $query->getCalculationMethod($attachment->CalculationMethodId)?->getName() : NULL,
    ];
    $processed->calculation_method = is_string($processed->calculation_method) ? strtolower($processed->calculation_method) : NULL;

    // Cleanup the values.
    $processed->values = array_map(function ($value) {
      return $value === "" ? NULL : $value;
    }, $processed->values);

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->getCustomIdWithRefCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomId() {
    return $this->custom_id;
  }

  /**
   * Get the custom id prefixed with the ref code.
   *
   * @return string
   *   The custom id prefixed with the ref code.
   */
  public function getCustomIdWithRefCode(): string {
    return $this->getPrototype()->getRefCode() . $this->getCustomId();
  }

  /**
   * Get the composed reference.
   *
   * @return string
   *   The composed reference.
   */
  public function getComposedReference(): string {
    return $this->getCustomIdWithRefCode();
  }

  /**
   * Get the timestamp for the last update.
   *
   * @return int
   *   A timestamp.
   */
  public function getLastUpdated() {
    return $this->getTimestamp($this->getRawData()->UpdatedAt);
  }

  /**
   * Get the type of attachment.
   *
   * @return string
   *   The type as string.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->source->entity_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType() {
    return $this->source->entity_type;
  }

  /**
   * Get the source entity type label.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityTypeLabel() {
    return match ($this->source->entity_type) {
      'plan' => $this->t('Plan'),
      'planEntity' => $this->t('Plan entity'),
      'governingEntity' => $this->t('Governing entity'),
    };
  }

  /**
   * Get the source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface|null
   *   The entity object.
   */
  public function getSourceEntity() {
    if (!empty($this->sourceEntity)) {
      return $this->sourceEntity;
    }
    $source_type = $this->getSourceEntityType();
    $source_id = $this->getSourceEntityId();
    if (!$source_type || !$source_id) {
      return NULL;
    }
    if ($source_type === 'plan') {
      $this->sourceEntity = $this->getPlanQuery()?->getPlan($source_id);
    }
    else {
      $this->sourceEntity = $this->getPlanEntityQuery()?->getEntity($source_type, $source_id);
    }
    return $this->sourceEntity;
  }

  /**
   * See if the attachment belongs to the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object to check.
   *
   * @return bool
   *   TRUE if the attachment belongs to the base object, FALSE otherwise.
   */
  public function belongsToBaseObject(BaseObjectInterface $base_object) {
    if ($this->getSourceEntityId() == $base_object->getSourceId()) {
      return TRUE;
    }
    $parent_base_object = $base_object instanceof BaseObjectChildInterface ? $base_object->getParentBaseObject() : NULL;
    if ($parent_base_object && $this->getSourceEntityId() == $parent_base_object->getSourceId()) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Get the current monitoring period for this attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  public function getCurrentMonitoringPeriod() {
    return $this->monitoring_period;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFields() {
    return $this->getPrototype()?->getOriginalFields() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFieldTypes() {
    return array_map(function ($item) {
      return $item->type;
    }, $this->getOriginalFields());
  }

  /**
   * Get the fields.
   *
   * @return array
   *   An array of field labels, keyed by their index.
   */
  public function getFields() {
    return $this->getPrototype()?->getFields() ?? [];
  }

  /**
   * Get the field types.
   *
   * @return string[]
   *   An array of field types, keyed by their index.
   */
  public function getFieldTypes() {
    return $this->getPrototype()?->getFieldTypes() ?? [];
  }

  /**
   * Get a data field by type.
   *
   * @param string $type
   *   The type of data point to retrieve.
   *
   * @return object
   *   The field as retrieved from the API.
   */
  public function getFieldByType($type) {
    $candidates = array_filter($this->getOriginalFields(), function ($item) use ($type) {
      return (strtolower($item->type) == strtolower($type));
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
  }

  /**
   * Get a field by it's index in the field list.
   *
   * @param int $index
   *   The index of the field to fetch.
   *
   * @return object|null
   *   The field if found.
   */
  public function getFieldByIndex($index) {
    $fields = $this->getOriginalFields();
    return $fields[$index] ?? NULL;
  }

  /**
   * Get the metric fields.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMetricFields() {
    return $this->getFields();
  }

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getGoalMetricFields() {
    $measurements = $this->getPrototype()?->getMeasurementMetricFields() ?? [];
    return array_filter($this->getFields(), function ($field) use ($measurements) {
      return !in_array($field, $measurements);
    });
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementMetricFields() {
    $measurements = $this->getPrototype()?->getMeasurementMetricFields() ?? [];
    return array_filter($this->getFields(), function ($field) use ($measurements) {
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
    $calculated_fields = $this->getPrototype()?->getCalculatedMetricFields() ?? [];
    return array_filter($this->getFields(), function ($field) use ($calculated_fields) {
      return in_array($field, $calculated_fields);
    });
  }

  /**
   * Get the source property for the calculated field.
   *
   * @param int $index
   *   The index of the data point in the list of all fields.
   *
   * @return string|null
   *   The source field type of the calculated field.
   */
  public function getSourceTypeForCalculatedField($index) {
    if (!$this->isCalculatedIndex($index)) {
      return NULL;
    }
    $original_fields = $this->getOriginalFields();
    return $original_fields[$index]?->source ?? NULL;
  }

  /**
   * Get the unit.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\Unit|null
   *   The unit object or NULL.
   */
  public function getUnit(): ?Unit {
    return $this->unit ?? NULL;
  }

  /**
   * Get the type of unit for an attachment.
   *
   * @return string|null
   *   The unit type as a string.
   */
  public function getUnitType(): ?string {
    return $this->getUnit()?->getType() ?? NULL;
  }

  /**
   * Get the label of the unit for an attachment.
   *
   * @return string|null
   *   The unit label as a string.
   */
  public function getUnitLabel($langcode = NULL): ?string {
    $unit = $this->getUnit();
    if (!$unit) {
      return NULL;
    }
    if ($langcode) {
      return $unit->getLocalizedName($langcode);
    }
    return $unit->getName();
  }

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getPrototype(): ?AttachmentPrototype {
    if ($this->prototype instanceof AttachmentPrototype) {
      return $this->prototype;
    }
    $attachment = $this->getRawData();

    // First see if we can extract the prototype from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    $query_handler = $this->getAttachmentPrototypeQuery();
    if (!$query_handler) {
      return NULL;
    }
    $plan_id = $attachment->PlanId ?? NULL;
    $prototype_id = $attachment->AttachmentPrototypeId ?? ($attachment->attachmentPrototypeId ?? NULL);
    if ($plan_id && $prototype_id && $prototype = $query_handler->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    $prototype = $prototype_id ? $query_handler->getPrototype($prototype_id) : NULL;
    if (!$prototype instanceof AttachmentPrototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $attachment->Id));
    }
    $this->prototype = $prototype;
    return $this->prototype;
  }

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isMeasurementIndex($index) {
    // We prefer looking at the prototype, if that fails, look directly at what
    // is in the attachmentVersion.
    $measurement_fields = $this->getPrototype()?->getMeasurementMetricFields() ?? $this->getMeasurementMetricFields();
    return array_key_exists($index, $measurement_fields);
  }

  /**
   * Check if the given field label represens a measurement metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a measurement, FALSE otherwise.
   */
  public function isMeasurementField($field_label) {
    return in_array($field_label, $this->getMeasurementMetricFields());
  }

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isCalculatedIndex($index) {
    return array_key_exists($index, $this->getCalculatedMetricFields());
  }

  /**
   * Check if the given field label represens a calculated metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a calculated metric, FALSE otherwise.
   */
  public function isCalculatedField($field_label) {
    return in_array($field_label, $this->getCalculatedMetricFields());
  }

  /**
   * Check if the given data point index represents a calculated metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a calculated metric, FALSE otherwise.
   */
  public function isCalculatedMeasurementIndex($index) {
    $calculated_fields = $this->getCalculatedMetricFields();
    $fields = $this->getOriginalFields();
    if (!array_key_exists($index, $calculated_fields) || !array_key_exists($index, $fields)) {
      return FALSE;
    }
    $source = $this->getSourceTypeForCalculatedField($index);
    if (!$source) {
      return FALSE;
    }
    $source_field = $this->getFieldByType($source);
    if (!$source_field) {
      return FALSE;
    }
    return $this->isMeasurementField($source_field->name->en);
  }

  /**
   * Check if the given field type string is considered cumulative reach.
   *
   * @param string $type
   *   The type string to check.
   *
   * @return bool
   *   TRUE if the type should be considered cumulative reach, FALSE otherwise.
   */
  private function isCumulativeReachFieldType($type) {
    return in_array($type, self::CUMULATIVE_REACH_FIELDS);
  }

  /**
   * See if data entry is still pending for this attachment.
   *
   * If there is no published reporting period yet, data entry is pending.
   * See https://humanitarian.atlassian.net/browse/HPC-5949
   *
   * @return bool
   *   TRUE if data entry is still pending, FALSE otherwise.
   */
  public function isPendingDataEntry() {
    return empty($this->getPlanReportingPeriods($this->getPlanId(), TRUE));
  }

  /**
   * Check if the given value should be considered NULL.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value should be considered NULL, FALSE otherwise.
   */
  public function isNullValue($value) {
    return empty($value) && $value !== 0 && $value !== "0";
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->map?->source?->plan_id ?? $this->getRawData()->PlanId;
  }

  /**
   * Get the plan object for this attachment.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan base object or NULL.
   */
  public function getPlanObject() {
    $plan_id = $this->getPlanId();
    $base_object = $plan_id ? BaseObjectHelper::getBaseObjectFromOriginalId($plan_id, 'plan') : NULL;
    return $base_object && $base_object instanceof Plan ? $base_object : NULL;
  }

  /**
   * Get the plan language.
   *
   * @return string|null
   *   The plan language code as a string or NULL.
   */
  public function getPlanLanguage() {
    return $this->getPlanObject()?->getPlanLanguage();
  }

  /**
   * Check if this attachment has data to show.
   *
   * @return bool
   *   TRUE if there is data, FALSE otherwise.
   */
  public function hasValues() {
    return !empty($this->getTotals());
  }

  /**
   * See if the API thinks that this attachment can have disaggregated data.
   *
   * @return bool
   *   TRUE if disaggregated data can be fetched, FALSE otherwise.
   */
  public function hasDisaggregatedData() {
    return (bool) $this->has_disaggregated_data;
  }

  /**
   * See if the attachment can be mapped for the given reporting period.
   *
   * @param int|string $reporting_period
   *   The reporting period id.
   *
   * @return bool
   *   TRUE if the attachment can be mapped, FALSE otherwise.
   */
  public function canBeMapped($reporting_period) {
    $disaggregated_data = $this->getDisaggregatedData($reporting_period, TRUE);
    foreach ($disaggregated_data->locations as $location) {
      if (empty($location->totals)) {
        continue;
      }
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check if a metric item is empty.
   *
   * It is considered empty if there are no locations with values.
   *
   * @param array $metric_item
   *   A metric item array.
   *
   * @return bool
   *   TRUE if the metric item can be considered empty, FALSE otherwise.
   */
  public function metricItemIsEmpty($metric_item): bool {
    // @todo See if this is still needed and if yes, then how.
    return FALSE;
  }

  /**
   * Get the disaggregated data from the attachment.
   *
   * @return object
   *   A disaggregated data object.
   */
  public function getDisaggregated(): object {
    $this->assureDisaggregatedData();
    $data = $this->getRawData();
    $facts = array_map(fn ($item) => new AttachmentFact($item), $data->disaggregated ?? []);
    return $this->buildDisaggregatedData($facts);
  }

  /**
   * Get the disaggregated data for multiple reporting periods.
   *
   * @param array $reporting_period_ids
   *   The reporting periods to process.
   * @param bool $filter_empty_locations
   *   Whether to exclude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exclude empty categories.
   *
   * @return array
   *   An array of disaggregated data arrays per reporting period.
   */
  public function getDisaggregatedDataMultiple(array $reporting_period_ids = [], $filter_empty_locations = FALSE, $filter_empty_categories = FALSE) {
    $map_data = [];
    $attachment_data = $this->getRawData();
    if (empty($attachment_data) || empty($reporting_period_ids)) {
      return $map_data;
    }
    $plan_id = $this->getPlanId();
    if (!$plan_id) {
      return $map_data;
    }
    $reporting_periods = self::getPlanReportingPeriods($plan_id, TRUE);
    if (empty($reporting_periods)) {
      return $map_data;
    }
    foreach ($reporting_period_ids as $reporting_period_id) {
      if (!array_key_exists($reporting_period_id, $reporting_periods)) {
        continue;
      }
      $disaggregated_data = $this->getDisaggregatedData($reporting_period_id, $filter_empty_locations, $filter_empty_categories);
      if (empty($disaggregated_data)) {
        continue;
      }
      $map_data[$reporting_period_id] = [
        'reporting_period' => $reporting_period_id == 'latest' ? end($reporting_periods) : $reporting_periods[$reporting_period_id],
        'disaggregated_data' => $disaggregated_data,
      ];
    }
    return $map_data;
  }

  /**
   * Get the disaggregated data for a data attachment.
   *
   * @param int|string $reporting_period
   *   Either the id of a period, or the string latest.
   * @param bool $filter_empty_locations
   *   Whether to exclude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exclude empty categories.
   * @param bool $ignore_missing_location_ids
   *   Whether to ignore locations with missing ids.
   *
   * @return object
   *   An object with disaggregated data.
   */
  public function getDisaggregatedData($reporting_period = 'latest', $filter_empty_locations = FALSE, $filter_empty_categories = FALSE, $ignore_missing_location_ids = FALSE): ?object {
    // First check if we have already processed this data.
    $cache_key = $this->getCacheKey([
      'attachment_id' => $this->id(),
      'reporting_period' => $reporting_period,
      'filter_empty_locations' => intval($filter_empty_locations),
      'filter_empty_categories' => intval($filter_empty_categories),
      'ignore_missing_location_ids' => intval($ignore_missing_location_ids),
      'updated' => $this->getLastUpdated(),
    ]);

    $cached_data = $this->cache($cache_key);
    if ($cached_data !== NULL) {
      return $cached_data;
    }

    // Get the disaggregated base data.
    $disaggregated = $this->getDisaggregated();

    // Get the disaggregated measurement data.
    $measurement = $this->getMeasurement($reporting_period);
    $disaggregated_measurements = $measurement?->getDisaggregated();

    // Load the locations that we actually need.
    $location_ids = array_merge(array_keys($disaggregated->locations), array_keys($disaggregated_measurements?->locations ?? []));
    $locations = !empty($location_ids) ? $this->getLocationQuery()->getLocations($location_ids) : [];

    $cache_tags = [];

    $data = (object) [
      'locations' => [],
      'metrics' => $disaggregated->metrics,
      'categories' => $disaggregated->categories,
    ];

    // Base data (disaggregated target totals).
    foreach ($locations as $location) {
      if ($location->isCountry()) {
        continue;
      }
      $location_id = $location->id();
      // Base data (disaggregated target totals).
      $data->locations[$location_id] = (object) [
        'location' => $location->getGeoJsonLocationData(),
        'totals' => $disaggregated->locations[$location_id]?->totals ?? [],
        'categories' => $disaggregated->locations[$location_id]?->categories ?? [],
      ];
      $cache_tags = Cache::mergeTags($cache_tags, $location->getCacheTags());
    }

    // Merge in the measurement data if available.
    if ($disaggregated_measurements) {
      foreach ($locations as $location) {
        if ($location->isCountry()) {
          continue;
        }
        $location_id = $location->id();
        if (empty($data->locations[$location_id])) {
          $data->locations[$location_id] = (object) [
            'location' => $location->getGeoJsonLocationData(),
            'totals' => $disaggregated_measurements->locations[$location_id]?->totals ?? [],
            'categories' => $disaggregated_measurements->locations[$location_id]?->categories ?? [],
          ];
          $cache_tags = Cache::mergeTags($cache_tags, $location->getCacheTags());
        }
        else {
          $data->locations[$location_id]->totals += $disaggregated_measurements->locations[$location_id]?->totals ?? [];
          foreach ($disaggregated_measurements->locations[$location_id]?->categories ?? [] as $category_id => $values) {
            $data->locations[$location_id]->categories[$category_id] = $data->locations[$location_id]->categories[$category_id] ?? [];
            $data->locations[$location_id]->categories[$category_id] += $values;
          }
        }
      }
      $data->metrics += $disaggregated_measurements->metrics;
      $data->categories += $disaggregated_measurements->categories;
    }

    $this->setCacheTags($cache_tags);
    return $this->cache($cache_key, $data, FALSE, NULL, $cache_tags);
  }

  /**
   * Retrieve the categories used in the disaggregation.
   *
   * @param int|string $reporting_period
   *   Either the id of a period, or the string latest.
   * @param int $property_index
   *   The index of the metric property.
   * @param bool $filter_empty_locations
   *   Whether to exclude empty locations.
   * @param bool $filter_empty_categories
   *   Whether to exclude empty categories.
   *
   * @return array
   *   Array with a list of category objects as retrieved from the API.
   */
  public function getDisaggregatedCategories($reporting_period, $property_index, $filter_empty_locations = FALSE, $filter_empty_categories = FALSE) {
    $disaggregated_data = $this->getDisaggregatedData($reporting_period, $filter_empty_locations, $filter_empty_categories);
    return $disaggregated_data->categories;
  }

  /**
   * Assure that the disaggregated data for an attachment has been fetched.
   */
  public function assureDisaggregatedData() {
    $data = $this->getRawData();
    if (property_exists($data, 'disaggregated')) {
      // Nothing to do.
      return;
    }
    $attachment_query = $this->getAttachmentQuery();
    $data->disaggregated = $attachment_query?->getAttachmentDisaggregatedData($this->id());
    if (!$data) {
      return;
    }
    $this->setRawData($data);
    $this->updateMap();
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @return array
   *   Array with values for each metric and measurement data point.
   */
  protected function extractValues(): array {
    $values = [];
    foreach ($this->getTotals() as $item) {
      $values[$item->getMetric()->getMachineName()] = $item->getValue();
    }
    return $values;
  }

  /**
   * Get the totals from the attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]
   *   An array of attachment fact objects.
   */
  public function getTotals(): array {
    $data = $this->getRawData();
    // Extract the values.
    return array_map(fn ($item) => !empty($item->MeasurementId) ? new MeasurementFact($item) : new AttachmentFact($item), $data->totals ?? []);
  }

  /**
   * Get the metrics from the given attachment.
   *
   * This fetches either the metrics from the attachment, or from a measurement
   * if a published one is already present.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]
   *   An array attachment fact objects.
   */
  protected function getMetrics(): array {
    // Get the totals from the attachment by default.
    return $this->getTotals();
    // phpcs:disable
    // // If there are measurements, look at the most recent one and get the
    // // metrics from there.
    // $measurement = self::getCurrentMeasurement();
    // if ($measurement) {
    //   $metrics = $measurement->metrics;
    // }
    // return $metrics;
    // phpcs:enable
  }

  /**
   * Get all measurements.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement[]|null
   *   An array of measurement objects or NULL.
   */
  public function getMeasurements() {
    $measurements = &drupal_static($this->getRawData()->Id . '::' . __METHOD__);
    if ($measurements) {
      return $measurements;
    }
    $attachment = $this->getRawData();
    if (empty($attachment->measurements)) {
      return NULL;
    }

    $measurements = array_map(function ($measurement) {
      return new Measurement($measurement);
    }, $attachment->measurements);
    ArrayHelper::sortObjectsByMethod($measurements, 'getReportingPeriodId', EndpointQuery::SORT_DESC);
    return $measurements;
  }

  /**
   * Get a single measurement object for the given period id.
   *
   * @param int|string $period_id
   *   The period id for the measurement or the string 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement|null
   *   A measurement object or NULL.
   */
  public function getMeasurement($period_id = 'latest'): ?Measurement {
    $measurements = $this->getMeasurements();
    if (!$measurements) {
      return NULL;
    }
    if ($period_id == 'latest') {
      return reset($measurements);
    }
    foreach ($measurements as $measurement) {
      if ($measurement->getReportingPeriodId() == $period_id) {
        return $measurement;
      }
    }
    return NULL;
  }

  /**
   * Get the current measurement.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement|null
   *   The measurement object or NULL.
   */
  public function getCurrentMeasurement() {
    // Get all measurements.
    $measurements = $this->getMeasurements();
    if (empty($measurements)) {
      return NULL;
    }
    // Limit this to the published measurements.
    $latest_published_period_id = $this->getPlanId() ? $this->getLatestPublishedReportingPeriod($this->getPlanId()) : NULL;
    if (!$latest_published_period_id) {
      return NULL;
    }
    $measurements = array_filter($measurements, function ($measurement) use ($latest_published_period_id) {
      return $measurement->getReportingPeriodId() == $latest_published_period_id;
    });
    return !empty($measurements) ? reset($measurements) : NULL;
  }

  /**
   * Get a specific measurement by the reporting period.
   *
   * @param int|string $reporting_period
   *   The reporting period id or the string 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement|null
   *   The measurement object or NULL.
   */
  protected function getMeasurementByReportingPeriod($reporting_period = 'latest') {
    if ($reporting_period == 'latest') {
      return $this->getCurrentMeasurement();
    }
    if ($reporting_period) {
      $measurements = $this->getMeasurements() ?? [];
      foreach ($measurements as $measurement) {
        if ($measurement->getReportingPeriodId() == $reporting_period) {
          return $measurement;
        }
      }
    }
    return NULL;
  }

  /**
   * Get a metric from the measurement specified by the reporting period.
   *
   * @param int $data_point
   *   The data point index.
   * @param int|string $reporting_period
   *   The id of the reporting period or the string 'latest'.
   *
   * @return int|float|null
   *   The value of the metric for the specified reporting period.
   */
  public function getMeasurementMetricValue($data_point, $reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    return $measurement?->getDataPointValue($data_point) ?? NULL;
  }

  /**
   * Get a comment tooltip for the current measurement.
   *
   * @param int|string $reporting_period
   *   The id of the reporting period or the string 'latest'.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface|null
   *   The value of the metric for the specified reporting period.
   */
  public function getMeasurementComment($reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    return $measurement?->getComment();
  }

  /**
   * Get a single specified reporting period object.
   *
   * @param int|string $period_id
   *   The reporting period id or the string 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  public function getReportingPeriod($period_id) {
    $plan_id = $this->getPlanId();
    return $plan_id ? self::getPlanReportingPeriod($plan_id, $period_id) : NULL;
  }

  /**
   * Get the reporting periods for the attachment.
   *
   * Can be optionally limited up to a specific monitoring period id.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]|null $reporting_periods
   *   The initial array of reporting periods or NULL.
   * @param int|string $monitoring_period
   *   A monitoring period id or the string 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of reporting period objects.
   */
  public function getReportingPeriods(?array $reporting_periods = NULL, $monitoring_period = 'latest') {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    assert(is_array($reporting_periods));
    if ($monitoring_period == 'latest') {
      return $reporting_periods;
    }
    while (!empty($reporting_periods) && array_key_last($reporting_periods) != $monitoring_period) {
      array_pop($reporting_periods);
    }
    return $reporting_periods;
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   An attachment prototype object.
   */
  protected function fetchPrototypeForAttachment($attachment) {
    // First see if we can extract the prototype from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    $query_handler = $this->getAttachmentPrototypeQuery();
    if (!$query_handler) {
      return NULL;
    }
    $plan_id = $attachment->PlanId ?? ($attachment->planId ?? NULL);
    $prototype_id = $attachment->AttachmentPrototypeId ?? ($attachment->attachmentPrototypeId ?? NULL);
    if ($plan_id && $prototype_id && $prototype = $query_handler->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    return $prototype_id ? $query_handler->getPrototype($prototype_id) : NULL;
  }

  /**
   * Fetch the reporting period for the given attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  protected function fetchReportingPeriodForAttachment(): ?PlanReportingPeriod {
    $plan_id = $this->getPlanId();
    if (!$plan_id) {
      return NULL;
    }
    $measurement = $this->getCurrentMeasurement();
    if (!$measurement) {
      return NULL;
    }
    return $this->getReportingPeriod($measurement->getReportingPeriodId());
  }

  /**
   * Get a value for a data point.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   *
   * @throws \Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException
   */
  public function getValue(array $conf) {
    $this->handleKnownConfigIssues($conf);
    switch ($conf['processing']) {
      case 'single':
        return $this->getSingleValue($conf['data_points'][0]['index'], NULL, $conf['data_points'][0]);

      case 'calculated':
        return $this->getCalculatedValue($conf);

      default:
        throw new InvalidAttachmentTypeException(sprintf('Unknown processing type: %s', $conf['processing']));
    }
  }

  /**
   * Get a single value for a data point.
   *
   * @param int $index
   *   The data point index.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   * @param array $data_point_conf
   *   An optional array with configuration for the specific data point to
   *   show.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   */
  public function getSingleValue($index, ?array $reporting_periods = NULL, $data_point_conf = []) {
    return $this->getValueForDataPoint($index, $data_point_conf['monitoring_period'] ?? NULL);
  }

  /**
   * Get the calculated value for a data point.
   *
   * @param array $conf
   *   The data point configuration.
   * @param object[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   */
  private function getCalculatedValue(array $conf, ?array $reporting_periods = NULL) {
    $value_1 = (float) $this->getSingleValue($conf['data_points'][0]['index'], $reporting_periods, $conf['data_points'][0]);
    $value_2 = (float) $this->getSingleValue($conf['data_points'][1]['index'], $reporting_periods, $conf['data_points'][1]);

    switch ($conf['calculation']) {
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
        throw new InvalidAttachmentTypeException(sprintf('Unknown calculation type: %s', $conf['calculation']));
    }

    return $final_value;
  }

  /**
   * Get a specific value for a data point in an attachment.
   *
   * @param int $data_point_index
   *   The index of the data point.
   * @param int|string $monitoring_period
   *   The id of the monitoring period or the string 'latest'.
   * @param bool $cumulative_logic
   *   Whether additional logic for data points of type cummulativeReach should
   *   be applied. This must be set to TRUE if called for example from
   *   self::getValuesForAllReportingPeriods() to prevent infinite recursion.
   *
   * @return mixed
   *   The data point value.
   */
  public function getValueForDataPoint($data_point_index, $monitoring_period = 'latest', $cumulative_logic = TRUE) {
    $value = NULL;
    if ($monitoring_period && $this->isMeasurementIndex($data_point_index)) {
      $measurement = $this->getMeasurement($monitoring_period);
      return $measurement?->getDataPointValue($data_point_index) ?? NULL;
    }

    $metric_type = $this->getPrototype()->getOriginalFields()[$data_point_index]?->type ?? NULL;
    $value = $metric_type ? ($this->values[$metric_type] ?? NULL) : NULL;

    $field = $this->getFieldByIndex($data_point_index);
    if ($value !== NULL || !$field) {
      return $value;
    }

    if ($this->isCumulativeReachFieldType($field->type) && $cumulative_logic) {
      // We have some specific logic for data points of type cummulativeReach.
      // If the current reporting period reports these as NULL, we want to
      // fetch the last non-NULL value from the other reporting periods of the
      // same attachment, if available.
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
      $period = $this->getLastNonEmptyReportingPeriod($data_point_index, $reporting_periods);
      if ($period && ($monitoring_period == 'latest' || $monitoring_period == array_key_last($reporting_periods)) && $period->id() != $monitoring_period) {
        $value = $this->getValueForDataPoint($data_point_index, $period->id());
      }

    }
    return $value;

  }

  /**
   * Get the values for all reporting periods of a data point.
   *
   * @param int $index
   *   The data point index.
   * @param bool $filter_empty
   *   Whether the values should be filtered.
   * @param bool $filter_null
   *   Whether the values should be filtered.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   *
   * @return mixed[]
   *   The data point values, extracted from the attachment according to the
   *   given configuration.
   */
  public function getValuesForAllReportingPeriods($index, $filter_empty = FALSE, $filter_null = FALSE, $reporting_periods = NULL) {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    $values = [];
    foreach ($reporting_periods as $reporting_period) {
      $value = $this->getValueForDataPoint($index, $reporting_period->id(), FALSE);
      if (empty($value) && $filter_empty) {
        continue;
      }
      if (empty($value) && $value !== 0 && $value !== "0" && $filter_null) {
        continue;
      }
      $values[$reporting_period->id()] = (int) $value;
    }
    return $values;
  }

  /**
   * Get the last reporting period with a non-empty value.
   *
   * @param int $index
   *   The data point index.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The monitoring period object or NULL if not found.
   */
  public function getLastNonEmptyReportingPeriod($index, $reporting_periods = NULL) {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    $values = $this->getValuesForAllReportingPeriods($index, TRUE, TRUE, $reporting_periods);
    $last_reporting_period_id = array_key_last($values);
    return $reporting_periods[$last_reporting_period_id] ?? NULL;
  }

  /**
   * Get a formatted value for a data point.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException
   */
  public function formatValue(array $conf) {
    // Prepare the build.
    $build = [
      '#type' => 'container',
      '#reporting_period' => $this->getLatestPublishedReportingPeriod($this->getPlanId()) ?? 'latest',
    ];
    $this->handleKnownConfigIssues($conf);
    // Create a render array for the actual value.
    if (empty($conf['widget']) || $conf['widget'] == 'none') {
      $build[] = $this->formatAsText($conf);
    }
    else {
      $build[] = $this->formatAsWidget($conf);
    }

    if (!empty($conf['data_points'][0]['monitoring_period'])) {
      $build['#reporting_period'] = $conf['data_points'][0]['monitoring_period'];
    }

    $data_point_index = $conf['data_points'][0]['index'];
    $field = $this->getFieldByIndex($data_point_index);
    if ($field && $this->isCumulativeReachFieldType($field->type)) {
      $period = $this->getLastNonEmptyReportingPeriod($data_point_index);
      $build['#reporting_period'] = $period?->id ?? $build['#reporting_period'];
    }

    // Prepare the tooltips.
    $build['tooltips'] = [
      '#theme' => 'hpc_tooltip_wrapper',
      '#tooltips' => [],
    ];

    // See if this needs a tooltip.
    $tooltip = $this->getTooltip($conf);
    if ($tooltip) {
      $build['tooltips']['#tooltips'] = $tooltip;
    }
    return $build;
  }

  /**
   * Check if the given field index represents cummulative reach data.
   *
   * This can either be if the index repesents a cummulative reach field
   * directly, or if the field is a calculated field with a cummulative reach
   * field as its source.
   *
   * @param int $index
   *   A metric index.
   *
   * @return bool
   *   TRUE if the given field index represents data coming from a cummulative
   *   reach field, FALSE otherwise.
   */
  public function isCummulativeReachField($index) {
    $field = $this->getFieldByIndex($index);
    $cumulative_reach_field = $field ? $this->isCumulativeReachFieldType($field->type) : FALSE;
    $cumulative_reach_source = $field ? $this->isCalculatedMeasurementIndex($index) && $this->isCumulativeReachFieldType($field->source) : FALSE;
    return $cumulative_reach_field || $cumulative_reach_source;
  }

  /**
   * Get the tooltip for a rendered data point of this attachment.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return array|null
   *   Either a build array for the tooltip, or NULL.
   */
  public function getTooltip($conf) {
    $index = $conf['data_points'][0]['index'];
    $value = $this->getSingleValue($index, NULL, $conf['data_points'][0]);
    if ($this->isNullValue($value)) {
      return NULL;
    }

    // See if this is a measurement and if we can get a formatted monitoring
    // period for this data point.
    $monitoring_period_id = $conf['data_points'][0]['monitoring_period'] ?? NULL;
    $format_string = NULL;
    if ($this->isCummulativeReachField($index)) {
      $format_string = '@data_range_cumulative';
      if ($monitoring_period_id == 'latest') {
        $monitoring_period_id = $this->getLastNonEmptyReportingPeriod($index)?->id() ?? $monitoring_period_id;
      }
    }
    $monitoring_tooltip = $this->isMeasurement($conf) ? $this->formatMonitoringPeriod('icon', $monitoring_period_id, $format_string) : NULL;

    // See if there is a comment.
    $comment = $this->isMeasurement($conf) ? $this->formatMeasurementCommentTooltip() : NULL;

    $tooltips = array_filter([
      'monitoring_period' => $monitoring_tooltip,
      'measurement_comment' => $comment,
    ]);
    if (empty($tooltips)) {
      return NULL;
    }
    return $tooltips;
  }

  /**
   * Check if the given data point configuration involves measurement fields.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return bool
   *   TRUE if any of the involved data points is a measurement, FALSE
   *   otherwise.
   */
  protected function isMeasurement(array $conf) {
    if ($this->isCalculatedMeasurement($conf)) {
      return TRUE;
    }
    $data_points = $conf['data_points'];
    $data_point_1 = $data_points[0]['index'];
    $data_point_2 = $data_points[1]['index'];
    switch ($conf['processing']) {
      case 'single':
        return $this->isMeasurementIndex($data_point_1);

      case 'calculated':
        return $this->isMeasurementIndex($data_point_1) || $this->isMeasurementIndex($data_point_2);

    }
    return FALSE;
  }

  /**
   * Check if the given data point configuration involves measurement fields.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return bool
   *   TRUE if any of the involved data points is a measurement, FALSE
   *   otherwise.
   */
  protected function isCalculatedMeasurement(array $conf) {
    $data_points = $conf['data_points'];
    $data_point_1 = $data_points[0]['index'];
    $data_point_2 = $data_points[1]['index'];
    switch ($conf['processing']) {
      case 'single':
        return $this->isCalculatedMeasurementIndex($data_point_1);

      case 'calculated':
        return $this->isCalculatedMeasurementIndex($data_point_1) || $this->isCalculatedMeasurementIndex($data_point_2);

    }
    return FALSE;
  }

  /**
   * Get a formatted text value for a data point.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException
   */
  private function formatAsText(array $conf) {
    $value = $this->getValue($conf);

    // Handle empty data by just "Pending" or "No data" for everything besides
    // percentage displays.
    if ($this->isNullValue($value) && $conf['formatting'] != 'percent') {
      $t_options = ['langcode' => $this->getPlanLanguage()];
      $value = $this->isPendingDataEntry() ? $this->t('Pending', [], $t_options) : $this->t('No data', [], $t_options);
      return [
        '#markup' => (string) $value,
      ];
    }

    $decimal_format = $conf['decimal_format'] ?? NULL;
    $rendered_value = NULL;
    switch ($conf['formatting']) {
      case 'raw':
        return [
          '#markup' => $value,
        ];

      case 'auto':
        if ($conf['processing'] == 'calculated' && $conf['calculation'] == 'percentage') {
          $rendered_value = [
            '#theme' => 'hpc_percent',
            '#ratio' => $value,
            '#decimals' => 1,
            '#decimal_format' => $decimal_format,
          ];
        }
        else {
          $rendered_value = [
            '#theme' => 'hpc_autoformat_value',
            '#value' => $value,
            '#unit_type' => $this->unit ? $this->unit->type : 'amount',
            '#unit_defaults' => [
              'amount' => [
                '#scale' => 'full',
              ],
            ],
            '#decimal_format' => $decimal_format,
          ];
        }
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
        throw new InvalidAttachmentTypeException(sprintf('Unknown formatting type: %s', $conf['formatting']));
    }

    return $rendered_value;
  }

  /**
   * Get a formatted widget for a data point.
   *
   * @param array $conf
   *   The data point configuration.
   *
   * @return mixed
   *   The data point value, extracted and formatted from the attachment
   *   according to the given configuration.
   *
   * @throws \Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException
   */
  private function formatAsWidget(array $conf) {
    switch ($conf['widget']) {
      case 'progressbar':
        $value = $this->getValue($conf);
        $widget = [
          '#theme' => 'hpc_progress_bar',
          '#ratio' => $value,
        ];
        break;

      case 'pie_chart':
        $value = $this->getValue($conf);
        $widget = [
          '#theme' => 'hpc_pie_chart',
          '#ratio' => $value,
        ];
        break;

      default:
        throw new InvalidAttachmentTypeException(sprintf('Unknown widget type: %s', $conf['widget']));
    }

    return $widget;
  }

  /**
   * Get a formatted monitoring period for the attachment object.
   *
   * @param string $display_type
   *   The display type, either "icon" or "text".
   * @param array $monitoring_period_id
   *   Optional: The id of the monitoring period.
   * @param string $format_string
   *   Optional: The format string used for the tooltip text.
   *
   * @return array|null
   *   A build array or NULL.
   */
  public function formatMonitoringPeriod($display_type, $monitoring_period_id = NULL, $format_string = NULL) {
    $monitoring_period = $monitoring_period_id ? $this->getReportingPeriod($monitoring_period_id) : $this->getCurrentMonitoringPeriod();
    if (!$monitoring_period) {
      return NULL;
    }
    $build = NULL;
    switch ($display_type) {
      case 'icon':
        $build = [
          '#theme' => 'hpc_tooltip',
          '#tooltip' => $monitoring_period->format($format_string),
          '#tag_content' => [
            '#theme' => 'hpc_icon',
            '#icon' => 'calendar_today',
            '#tag' => 'span',
          ],
        ];
        break;

      case 'text':
        $build = $monitoring_period->format($format_string);
        break;
    }
    return $build;
  }

  /**
   * Get a formatted measurement comment tooltip.
   *
   * @return array|null
   *   A build array or NULL.
   */
  public function formatMeasurementCommentTooltip() {
    $comment = $this->getMeasurementComment();
    if (!$comment) {
      return NULL;
    }
    return [
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $comment,
      '#tooltip_theme' => 'measurement-comment',
    ];
  }

  /**
   * Fix some known issues with existing config.
   *
   * @param array $conf
   *   A configuration object for a data point.
   */
  private function handleKnownConfigIssues(array &$conf) {
    // Sanity check to cope with invalid configuration.
    if (!empty($conf['data_points'][0]['monitoring_period']) && is_object($conf['data_points'][0]['monitoring_period'])) {
      $conf['data_points'][0]['monitoring_period'] = $conf['data_points'][0]['monitoring_period']->monitoring_period ?? 'latest';
    }
    if (!empty($conf['data_points'][1]['monitoring_period']) && is_object($conf['data_points'][1]['monitoring_period'])) {
      $conf['data_points'][1]['monitoring_period'] = $conf['data_points'][1]['monitoring_period']->monitoring_period ?? 'latest';
    }
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
      'percentage' => t('Percentage (data point 1 * (100 / data point 2))'),
      'addition' => t('Sum (data point 1 + data point 2)'),
      'substraction' => t('Substraction (data point 1 - data point 2)'),
      'division' => t('Division (data point 1 / data point 2)'),
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
      'auto' => t('Automatic based on the unit (uses percentage for percentages, amount for all others)'),
      'currency' => t('Currency value'),
      'amount' => t('Amount value'),
      'amount_rounded' => t('Amount value (rounded, 1 decimal)'),
      'percent' => t('Percentage value'),
      'raw' => t('Raw data (no formatting)'),
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
