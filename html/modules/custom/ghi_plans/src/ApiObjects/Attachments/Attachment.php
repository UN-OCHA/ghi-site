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
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\ApiObjects\Types\Unit;
use Drupal\hpc_api\Traits\DateTimeTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_api\Helpers\StringHelper;

/**
 * Abstraction for API data attachment objects.
 */
class Attachment extends ApiObjectBase implements AttachmentInterface, DisaggregatedDataInterface {

  use DataPointConfigBackwardsCompatibilityTrait;
  use DateTimeTrait;
  use DisaggregatedDataTrait;
  use PlanQueryTrait;
  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;
  use StringTranslationTrait;

  /**
   * The type of the attachment.
   *
   * @var string
   */
  protected string $type;

  /**
   * The plan id.
   *
   * @var int|null
   */
  protected ?int $planId;

  /**
   * The entity id.
   *
   * @var int|null
   */
  protected ?int $entityId;

  /**
   * The entity type id.
   *
   * @var int|null
   */
  protected ?int $entityTypeId;

  /**
   * The entity main type.
   *
   * @var string|null
   */
  protected ?string $entityMainType;

  /**
   * The source data.
   *
   * @var object
   */
  protected object $source;

  /**
   * The attachment prototype id.
   *
   * @var int|null
   */
  protected ?int $attachmentPrototypeId;

  /**
   * The custom id.
   *
   * @var string|null
   */
  protected ?string $customId;

  /**
   * The composed reference.
   *
   * @var string|null
   */
  protected ?string $composedReference;

  /**
   * The description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * The values.
   *
   * @var array
   */
  protected array $values;

  /**
   * The unit.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\Unit|null
   */
  protected ?Unit $unit;

  /**
   * The monitoring period.
   *
   * @var \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   */
  protected ?PlanReportingPeriod $monitoringPeriod;

  /**
   * Whether the attachment has disaggregated data.
   *
   * @var bool
   */
  protected bool $hasDisaggregatedData;

  /**
   * The calculation method.
   *
   * @var string|null
   */
  protected ?string $calculationMethod;

  /**
   * The timestamp of the last update.
   *
   * @var string|null
   */
  protected ?string $updatedAt;

  /**
   * The facts representing the totals.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]
   */
  protected $totals;

  /**
   * The disaggregated data.
   *
   * @var object
   */
  protected $disaggregated;

  /**
   * The facts representing the totals.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Measurements\Measurement[]
   */
  protected $measurements;

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
    'CalculationMethod',
    'Description',
    'AttachmentPrototypeId',
    'UpdatedAt',
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    'attachmentFact' => [
      'filter' => ['IsTotal' => TRUE, 'LocationId' => NULL],
      'items' => AttachmentFact::GRAPHQL_ITEMS,
    ],
    'measurement' => ['items' => Measurement::GRAPHQL_ITEMS],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * Define the properties used for storage lookups.
   */
  const LOOKUP_PROPERTIES = [
    'Name',
    'PlanId',
    'EntityId',
    'EntityTypeId',
    'EntityMainType',
    'AttachmentType',
  ];

  const OBJECT_STORAGE_KEY = 'AttachmentObjectStorage';

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $query = $this->getEntityTypeQuery();
    $this->processTotals((array) ($data->attachmentFact?->items ?? []));
    $this->processMeasurements((array) ($data->measurement?->items ?? []));
    $calculation_method = $data->CalculationMethod ?? NULL;

    // Extract and cleanup the values.
    $values = $this->extractValues($this->totals ?? []);
    $values = array_map(function ($value) {
      return $value === "" ? NULL : $value;
    }, $values);

    $this->type = strtolower($data->AttachmentType);
    $this->planId = $data->PlanId ?? NULL;
    $this->entityId = $data->EntityId ?? NULL;
    $this->entityTypeId = $data->EntityTypeId ?? NULL;
    $this->entityMainType = $data->EntityMainType ?? NULL;
    $this->source = (object) [
      'entity_type' => PlanEntityHelper::checkObjectType($data->EntityMainType ?? NULL),
      'entity_id' => $data->EntityId ?? NULL,
      'plan_id' => $data->PlanId ?? NULL,
    ];
    $this->attachmentPrototypeId = $data->AttachmentPrototypeId;
    $this->customId = $data->CustomReference ?? NULL;
    $this->composedReference = $data->ComposedReference ?? NULL;
    $this->description = $data->Name ?? NULL;
    $this->values = $values;
    $this->unit = ($data->UnitId ?? NULL) ? $query->getUnit($data->UnitId) : NULL;
    $this->monitoringPeriod = NULL;
    $this->hasDisaggregatedData = !empty($data->HasDisaggregatedData);
    $this->calculationMethod = is_string($calculation_method) ? strtolower($calculation_method) : NULL;
    $this->updatedAt = $data->UpdatedAt ?? NULL;
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
    return $this->customId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomIdWithRefCode(): string {
    return $this->getPrototype()?->getRefCode() . $this->getCustomId();
  }

  /**
   * {@inheritdoc}
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
  public function getLastUpdated(): ?int {
    return $this->updatedAt ? self::getTimestamp($this->updatedAt) : NULL;
  }

  /**
   * Get the type of attachment.
   *
   * @return string
   *   The type as string.
   */
  public function getAttachmentType() {
    return $this->type;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityId() {
    return $this->entityId;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityMainType() {
    return $this->entityMainType;
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
   * {@inheritdoc}
   */
  public function getSourceEntity(): ?PlanEntityInterface {
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
      $this->sourceEntity = $this->getEntityQuery()?->getEntity($source_type, $source_id);
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
    if ($this->monitoringPeriod !== NULL) {
      return $this->monitoringPeriod;
    }
    $this->monitoringPeriod = $this->fetchReportingPeriodForAttachment();
    return $this->monitoringPeriod;
  }

  /**
   * {@inheritdoc}
   */
  public function getFields() {
    return $this->getPrototype()?->getFields() ?? [];
  }

  /**
   * {@inheritdoc}
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
   * @return string
   *   The field label as retrieved from the API.
   */
  public function getFieldByType($type): string {
    $fields = $this->getFields();
    return $fields[$type] ?? NULL;
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
    $fields = array_values($this->getFields());
    return $fields[$index] ?? NULL;
  }

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getPlanningFields() {
    return $this->getPrototype()?->getPlanningFields() ?? [];
  }

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementFields() {
    return $this->getPrototype()?->getMeasurementFields() ?? [];
  }

  /**
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedFields() {
    return $this->getPrototype()?->getCalculatedFields() ?? [];
  }

  /**
   * Get the source property for the calculated field.
   *
   * @param string $metric_type
   *   The metric type of the data point.
   *
   * @return string|null
   *   The source field type of the calculated field.
   */
  public function getSourceTypeForCalculatedField($metric_type) {
    $original_fields = $this->getPrototype()?->getOriginalFields() ?? [];
    return $original_fields[$metric_type]?->source ?? NULL;
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
   * Get the id of the attachment prototype.
   *
   * @return int|null
   *   The attachment prototype id.
   */
  public function getPrototypeId(): ?int {
    return $this->attachmentPrototypeId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?AttachmentPrototype {
    if ($this->prototype instanceof AttachmentPrototype) {
      return $this->prototype;
    }

    // First see if we can extract the prototype from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    $attachment_prototype_query = $this->getAttachmentPrototypeQuery();
    if (!$attachment_prototype_query) {
      return NULL;
    }
    $plan_id = $this->getPlanId();
    $prototype_id = $this->getPrototypeId();
    if ($plan_id && $prototype_id && $prototype = $attachment_prototype_query->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    $prototype = $prototype_id ? $attachment_prototype_query->getPrototype($prototype_id) : NULL;
    if (!$prototype instanceof AttachmentPrototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $this->id()));
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
    $field_types = $this->getFieldTypes();
    return !empty($field_types[$index]) ? $this->isMeasurementField($field_types[$index]) : FALSE;
  }

  /**
   * Check if the given field label represens a measurement metric.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return bool
   *   TRUE if the field is a measurement, FALSE otherwise.
   */
  public function isMeasurementField($field_type) {
    return array_key_exists($field_type, $this->getMeasurementFields());
  }

  /**
   * Check if the given field label represens a calculated field.
   *
   * @param string $metric_type
   *   The metric type.
   *
   * @return bool
   *   TRUE if the field is a calculated field, FALSE otherwise.
   */
  public function isCalculatedField($metric_type) {
    return array_key_exists($metric_type, $this->getCalculatedFields());
  }

  /**
   * Check if the given field label represens a calculated measurement metric.
   *
   * @param string $metric_type
   *   The metric type.
   *
   * @return bool
   *   TRUE if the field is a calculated measurement metric, FALSE otherwise.
   */
  public function isCalculatedMeasurmentField($metric_type) {
    if (!array_key_exists($metric_type, $this->getCalculatedFields())) {
      return FALSE;
    }
    $fields = $this->getPrototype()?->getOriginalFields();
    $field = $fields[$metric_type] ?? NULL;
    if (!$field) {
      return FALSE;
    }
    $source = $this->getSourceTypeForCalculatedField($metric_type);
    if (!$source) {
      return FALSE;
    }
    $source_field = $this->getFieldByType($source);
    if (!$source_field) {
      return FALSE;
    }
    return $this->isMeasurementField($source_field);
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
  private function isCumulativeReachFieldType(string $type): bool {
    return in_array(StringHelper::makeCamelCase($type, TRUE), self::CUMULATIVE_REACH_FIELDS);
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
  public function isPendingDataEntry(): bool {
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
  public function isNullValue($value): bool {
    return empty($value) && $value !== 0 && $value !== 0.0 && $value !== "0";
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->source?->plan_id ?? $this->planId;
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
    return !empty($this->values);
  }

  /**
   * {@inheritdoc}
   */
  public function canHaveDisaggregatedData(): bool {
    return (bool) $this->hasDisaggregatedData;
  }

  /**
   * See if the attachment has disaggregated data available.
   *
   * @return bool
   *   TRUE if disaggregated data can be fetched, FALSE otherwise.
   */
  public function hasDisaggregatedData() {
    return $this->canHaveDisaggregatedData() && $this->getAttachmentQuery()->hasDisaggregatedData($this->id());
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
    $disaggregated_data = $this->getDisaggregatedData($reporting_period);
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
    if (!$this->disaggregated) {
      $facts = [];
      if ($this->canHaveDisaggregatedData()) {
        $attachment_query = $this->getAttachmentQuery();
        $disaggregated_data = $attachment_query?->getAttachmentDisaggregatedData($this->id());
        $facts = array_map(fn ($item) => new AttachmentFact($item), (array) ($disaggregated_data ?: []));
      }
      $this->disaggregated = $this->buildDisaggregatedData($facts);
    }
    return $this->disaggregated;
  }

  /**
   * Get the disaggregated data for multiple reporting periods.
   *
   * @param array $reporting_period_ids
   *   The reporting periods to process.
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   Optional metric type to filter by.
   *
   * @return array
   *   An array of disaggregated data arrays per reporting period.
   */
  public function getDisaggregatedDataMultiple(array $reporting_period_ids = [], ?MetricType $metric_type = NULL) {
    $map_data = [];
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
      $disaggregated_data = $this->getDisaggregatedData($reporting_period_id, $metric_type);
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
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   Optional metric type to filter by.
   *
   * @return object
   *   An object with disaggregated data.
   */
  public function getDisaggregatedData($reporting_period = 'latest', ?MetricType $metric_type = NULL): ?object {
    // First check if we have already processed this data.
    $cache_key = $this->getCacheKey(array_filter([
      'attachment_id' => $this->id(),
      'reporting_period' => $reporting_period,
      'updated' => $this->getLastUpdated(),
    ]));

    $data = $this->cache($cache_key);
    if (!$data) {
      // Get the disaggregated base data.
      $disaggregated = $this->getDisaggregated();
      // Get the disaggregated measurement data.
      $measurement = $this->getMeasurement($reporting_period);
      $disaggregated_measurements = $measurement?->getDisaggregated();

      // Load the locations that we actually need.
      $location_ids = array_merge(array_keys($disaggregated->locations), array_keys($disaggregated_measurements?->locations ?? []));
      $locations = !empty($location_ids) ? ($this->getLocationQuery()?->getLocationsById($location_ids) ?? []) : [];

      $cache_tags = [];

      $data = (object) [
        'locations' => [],
        'metrics' => $disaggregated->metrics + ($disaggregated_measurements?->metrics ?? []),
        'categories' => $disaggregated->categories + ($disaggregated_measurements?->categories ?? []),
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
      $this->cache($cache_key, $data, FALSE, NULL, $cache_tags);
    }
    if ($metric_type) {
      $this->filterDisaggregatedData($data, $metric_type);
    }

    return $data;
  }

  /**
   * Process the totals.
   *
   * @param array $measurements
   *   An array of raw measurement objects.
   */
  protected function processMeasurements(array $measurements) {
    if ($this->measurements !== NULL || empty($measurements)) {
      return;
    }

    $this->measurements = [];
    foreach (array_map(fn ($item): Measurement => new Measurement($item), $measurements) as $measurement) {
      $this->measurements[$measurement->id()] = $measurement;
    }
    ArrayHelper::sortObjectsByMethod($this->measurements, 'getReportingPeriodId', SORT_DESC);
  }

  /**
   * Process the totals.
   *
   * @param array $totals
   *   An array of raw fact objects.
   */
  protected function processTotals(array $totals) {
    if ($this->totals !== NULL || empty($totals)) {
      return;
    }
    $this->totals = array_map(fn ($item) => !empty($item->MeasurementId) ? new MeasurementFact($item) : new AttachmentFact($item), $totals);
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[] $totals
   *   The totals to use for value extraction.
   *
   * @return array
   *   Array with values for each metric and measurement data point.
   */
  protected function extractValues(array $totals = []): array {
    $values = [];
    foreach ($totals as $item) {
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
    return $this->totals ?: [];
  }

  /**
   * Get all measurements.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement[]
   *   An array of measurement objects.
   */
  public function getMeasurements() {
    return $this->measurements ?: [];
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
  public function getCurrentMeasurement(): ?Measurement {
    $plan_id = $this->getPlanId();
    if (!$plan_id) {
      return NULL;
    }
    // Get all measurements.
    $measurements = $this->getMeasurements();
    if (empty($measurements)) {
      return NULL;
    }

    // Find the latest reporting period id from the plan.
    $latest_published_period_id = $this->getLatestPublishedReportingPeriod($plan_id);
    if (!$latest_published_period_id) {
      return NULL;
    }
    // And find the measurement for that period.
    foreach ($measurements as $measurement) {
      if ($measurement->getReportingPeriodId() == $latest_published_period_id) {
        return $measurement;
      }
    }
    return NULL;
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
  protected function getMeasurementByReportingPeriod($reporting_period = 'latest'): ?Measurement {
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
   * @param string $metric_type
   *   The metric type of the data point.
   * @param int|string $reporting_period
   *   The id of the reporting period or the string 'latest'.
   *
   * @return int|float|null
   *   The value of the metric for the specified reporting period.
   */
  public function getMeasurementMetricValue($metric_type, $reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    return $measurement?->getDataPointValue($metric_type) ?? NULL;
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
   * Fetch the reporting period for the given attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  protected function fetchReportingPeriodForAttachment(): ?PlanReportingPeriod {
    $measurement = $this->getCurrentMeasurement();
    if (!$measurement) {
      return NULL;
    }
    return $this->getReportingPeriod($measurement->getReportingPeriodId());
  }

  /**
   * Get all current values for this attachment.
   *
   * @return array
   *   An array of values keyed by the metric type.
   */
  public function getPlanningValues() {
    return array_filter($this->values, fn ($metric_type) => !$this->isMeasurementField($metric_type), ARRAY_FILTER_USE_KEY);
  }

  /**
   * Get all current values for this attachment.
   *
   * @return array
   *   An array of values keyed by the metric type.
   */
  public function getCurrentValues() {
    $measurement = $this->getCurrentMeasurement();
    $plan_object = $this->getPlanObject();
    if ($measurement === NULL && $plan_object !== NULL && $plan_object->getYear() < date('Y') && count($this->getMeasurements())) {
      // If there is no current measurement, the plan is from last year or
      // earlier and there are at least some measurements, take the values
      // from the most recent measurement.
      $measurements = $this->getMeasurements();
      $measurement = reset($measurements);
    }
    $values = $this->values + ($measurement?->getValues() ?? []);
    return $values;
  }

  /**
   * Get all measurement values for this attachment.
   *
   * @return array
   *   An array of arrays, first level keys are the reporting periods, second
   *   level are the values per metric type.
   */
  public function getMeasurementValues() {
    $values = [];
    foreach ($this->getMeasurements() as $measurement) {
      $values[$measurement->getReportingPeriodId()] = $measurement->getValues() + $this->getPlanningValues();
    }
    ksort($values);
    return $values;
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
    $cache_key = $this->getCacheKey([
      'attachment_id' => $this->id(),
      'conf' => json_encode($conf),
    ]);
    $value = $this->cache($cache_key);
    if ($value !== NULL) {
      return $value;
    }
    $this->handleKnownConfigIssues($conf);
    if (empty($conf['data_points'][0]['metric_type'])) {
      return NULL;
    }
    switch ($conf['processing']) {
      case 'single':
        $value = $this->getSingleValue($conf['data_points'][0]['metric_type'], NULL, $conf['data_points'][0]);
        break;

      case 'calculated':
        $value = $this->getCalculatedValue($conf);
        break;

      default:
        throw new InvalidAttachmentTypeException(sprintf('Unknown processing type: %s', $conf['processing']));
    }
    return $this->cache($cache_key, $value);
  }

  /**
   * Get a specific data point value by type in an attachment.
   *
   * @param string $metric_type
   *   The metric type of the data point.
   * @param int|string $monitoring_period
   *   The id of the monitoring period or the string 'latest'.
   * @param bool $cumulative_logic
   *   Whether additional logic for data points of type cumulativeReach should
   *   be applied. This must be set to TRUE if called for example from
   *   self::getValuesForAllReportingPeriods() to prevent infinite recursion.
   *
   * @return mixed
   *   The data point value.
   */
  public function getValueByMetricType(string $metric_type, $monitoring_period = 'latest', $cumulative_logic = TRUE) {
    $value = NULL;
    if ($monitoring_period && $this->isMeasurementField($metric_type)) {
      $measurement = $this->getMeasurement($monitoring_period);
      return $measurement?->getDataPointValue($metric_type) ?? NULL;
    }
    $values = $this->getCurrentValues();
    $value = $values[$metric_type] ?? NULL;

    if ($this->isCumulativeReachFieldType($metric_type) && $cumulative_logic) {
      // We have some specific logic for data points of type cumulativeReach.
      // If the current reporting period reports these as NULL, we want to
      // fetch the last non-NULL value from the other reporting periods of the
      // same attachment, if available.
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
      $period = $this->getLastNonEmptyReportingPeriod($metric_type, $reporting_periods);
      if ($period && ($monitoring_period == 'latest' || $monitoring_period == array_key_last($reporting_periods)) && $period->id() != $monitoring_period) {
        $value = $this->getValueByMetricType($metric_type, $period->id());
      }

    }
    return $value;
  }

  /**
   * Get a specific data point value by index in an attachment.
   *
   * @param int $index
   *   The index of the data point.
   * @param int|string $monitoring_period
   *   The id of the monitoring period or the string 'latest'.
   * @param bool $cumulative_logic
   *   Whether additional logic for data points of type cumulativeReach should
   *   be applied. This must be set to TRUE if called for example from
   *   self::getValuesForAllReportingPeriods() to prevent infinite recursion.
   *
   * @return mixed
   *   The data point value.
   */
  public function getValueByIndex($index, $monitoring_period = 'latest', $cumulative_logic = TRUE) {
    $metric_type = array_values($this->getFieldTypes())[$index] ?? NULL;
    return $metric_type ? $this->getValueByMetricType($metric_type, $monitoring_period, $cumulative_logic) : NULL;
  }

  /**
   * Get a single value for a data point.
   *
   * @param string $metric_type
   *   The metric type.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   * @param array $conf
   *   An optional array with configuration for the specific data point to
   *   show.
   *
   * @return mixed
   *   The data point value, extracted from the attachment according to the
   *   given configuration.
   */
  public function getSingleValue(string $metric_type, ?array $reporting_periods = NULL, $conf = []) {
    return $this->getValueByMetricType($metric_type, $conf['monitoring_period'] ?? NULL);
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
    $value_1 = (float) $this->getSingleValue($conf['data_points'][0]['metric_type'], $reporting_periods, $conf['data_points'][0]);
    $value_2 = (float) $this->getSingleValue($conf['data_points'][1]['metric_type'], $reporting_periods, $conf['data_points'][1]);

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
   * Get the values for all reporting periods of a data point.
   *
   * @param string $metric_type
   *   The metric type.
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
  public function getValuesForAllReportingPeriods(string $metric_type, bool $filter_empty = FALSE, bool $filter_null = FALSE, ?array $reporting_periods = NULL) {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    $values = [];
    foreach ($reporting_periods as $reporting_period) {
      $value = $this->getValueByMetricType($metric_type, $reporting_period->id(), FALSE);
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
   * @param string $metric_type
   *   The metric type.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   An optional array of reporting period objects. If not provided, all
   *   reporting periods from the plan will be used.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The monitoring period object or NULL if not found.
   */
  public function getLastNonEmptyReportingPeriod($metric_type, $reporting_periods = NULL) {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    $values = $this->getValuesForAllReportingPeriods($metric_type, TRUE, TRUE, $reporting_periods);
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

    $metric_type = $conf['data_points'][0]['metric_type'];
    if ($metric_type && $this->isCumulativeReachFieldType($metric_type)) {
      $period = $this->getLastNonEmptyReportingPeriod($metric_type);
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
   * Check if the given field metric type represents cumulative reach data.
   *
   * This can either be if the metric type repesents a cumulative reach field
   * directly, or if the field is a calculated field with a cumulative reach
   * field as its source.
   *
   * @param string $metric_type
   *   A metric type.
   *
   * @return bool
   *   TRUE if the given field metric type represents data coming from a
   *   cumulative reach field, FALSE otherwise.
   */
  public function isCumulativeReachField($metric_type) {
    $cumulative_reach_field = $this->isCumulativeReachFieldType($metric_type);
    $field = $this->getPrototype()?->getOriginalFields()[$metric_type] ?? NULL;
    $cumulative_reach_source = $this->isCalculatedField($metric_type) && $field && $this->isCumulativeReachFieldType($field->source);
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
    $this->handleKnownConfigIssues($conf);
    $metric_type = $conf['data_points'][0]['metric_type'];
    if (empty($metric_type)) {
      return NULL;
    }
    $value = $this->getSingleValue($metric_type, NULL, $conf['data_points'][0]);
    if ($this->isNullValue($value)) {
      return NULL;
    }

    // See if this is a measurement and if we can get a formatted monitoring
    // period for this data point.
    $monitoring_period_id = $conf['data_points'][0]['monitoring_period'] ?? NULL;
    $format_string = NULL;
    if ($this->isCumulativeReachFieldType($metric_type)) {
      $format_string = '@data_range_cumulative';
      if ($monitoring_period_id == 'latest') {
        $monitoring_period_id = $this->getLastNonEmptyReportingPeriod($metric_type)?->id() ?? $monitoring_period_id;
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
    $data_point_1 = $data_points[0]['metric_type'];
    $data_point_2 = $data_points[1]['metric_type'];
    switch ($conf['processing']) {
      case 'single':
        return $this->isMeasurementField($data_point_1);

      case 'calculated':
        return $this->isMeasurementField($data_point_1) || $this->isMeasurementField($data_point_2);

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
    $data_point_1 = $data_points[0]['metric_type'];
    $data_point_2 = $data_points[1]['metric_type'];
    switch ($conf['processing']) {
      case 'single':
        return $this->isCalculatedMeasurmentField($data_point_1);

      case 'calculated':
        return $this->isCalculatedMeasurmentField($data_point_1) || $this->isCalculatedMeasurmentField($data_point_2);

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
            '#unit_type' => $this->unit?->getType() ?: 'amount',
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
  public function handleKnownConfigIssues(array &$conf) {
    // Sanity check to cope with invalid configuration.
    if ($prototype = $this->getPrototype()) {
      $this->updateDataPointConfiguration($conf, $prototype);
    }
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
