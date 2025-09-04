<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Measurements\Measurement;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Exceptions\InvalidAttachmentTypeException;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_api\Traits\SimpleCacheTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Abstraction for API data attachment objects.
 */
class DataAttachment extends AttachmentBase implements DataAttachmentInterface {

  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;

  /**
   * The source entity of an attachment.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface
   */
  private $sourceEntity;

  /**
   * Define the ID that is used for unit objects of type percentage.
   *
   * @todo Should be removed once HPC-5754 is done.
   */
  const UNIT_TYPE_ID_PERCENTAGE = 17;

  /**
   * Define a list of field types that should be considered cumulative reach.
   */
  const CUMULATIVE_REACH_FIELDS = [
    'cumulativeReach',
    'optionNonPlanCumulReach',
    'optionOverallCumulReach',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $attachment = $this->getRawData();
    $metrics = $this->getMetrics();
    $unit = $metrics && is_object($metrics) && property_exists($metrics, 'unit') ? ($metrics->unit->object ?? NULL) : NULL;
    $prototype = $this->getPrototypeData();
    $period = $this->fetchReportingPeriodForAttachment();
    $references = property_exists($attachment, 'composedReference') ? explode('/', $attachment->composedReference) : [];

    // Extract the values.
    $totals = $metrics?->values?->totals ?? [];
    $metric_fields = array_filter($totals, function ($index) use ($prototype) {
      return !array_key_exists($index, $prototype->getMeasurementMetricFields());
    }, ARRAY_FILTER_USE_KEY);
    $measurement_fields = $metrics?->measureFields ?? [];
    $calculated_fields = $metrics?->calculatedFields ?? [];

    // Work around an issue with the API format for this.
    $calculated_fields = is_array($calculated_fields) ? $calculated_fields : [$calculated_fields];

    // Put all fields together.
    $all_fields = array_merge(
      $metric_fields,
      $measurement_fields,
      $calculated_fields,
    );

    $processed = (object) [
      'id' => $attachment->id,
      'type' => strtolower($attachment->type),
      'source' => (object) [
        'entity_type' => PlanEntityHelper::checkObjectType($attachment->objectType ?? NULL),
        'entity_id' => $attachment->objectId ?? NULL,
        'plan_id' => $attachment->planId ?? NULL,
      ],
      'custom_id' => $attachment->attachmentVersion?->value?->customId ?? ($attachment->attachmentVersion?->customReference ?? NULL),
      'custom_id_prefixed_refcode' => end($references),
      'composed_reference' => $attachment->composedReference ?? NULL,
      'description' => $attachment->attachmentVersion?->value?->description ?? NULL,
      'values' => $this->extractValues(),
      'prototype' => $prototype,
      'unit' => $unit ? (object) [
        'label' => $unit->label ?? NULL,
        'type' => $unit->id == self::UNIT_TYPE_ID_PERCENTAGE ? 'percentage' : 'amount',
        'group' => property_exists($unit, 'isGender') && $unit->isGender == 1 ? 'people' : 'amount',
        'locale' => [
          'en' => $unit->label ?? NULL,
          'fr' => $unit->labelFr ?? NULL,
        ],
      ] : NULL,
      'monitoring_period' => $period ?? NULL,
      'fields' => $prototype->getFields(),
      'field_types' => $prototype->getFieldTypes(),
      'original_fields' => $all_fields,
      'original_field_types' => array_map(function ($item) {
        return $item->type;
      }, $all_fields ?? []),
      'measurement_fields' => $measurement_fields ? array_map(function ($field) {
        return $field->name->en;
      }, $measurement_fields) : [],
      'calculated_fields' => $calculated_fields ? array_map(function ($field) {
        return $field->name->en;
      }, $calculated_fields) : [],
      'totals' => $totals,
      'has_disaggregated_data' => !empty($attachment->attachmentVersion?->hasDisaggregatedData),
      'disaggregated' => $attachment->attachmentVersion?->value?->metrics?->values?->disaggregated ?? NULL,
      'calculation_method' => $attachment->attachmentVersion?->value?->metrics?->calculationMethod ?? NULL,
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
    return $this->composed_reference;
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
   * Get the type of attachment.
   *
   * @return string
   *   The type as string.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get the source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface|null
   *   The entity object.
   */
  public function getSourceEntity() {
    if (empty($this->source->entity_type) || empty($this->source->entity_id)) {
      return NULL;
    }
    if (empty($this->sourceEntity) && $entityQuery = $this->getEndpointQueryManager()->createInstance('entity_query')) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery $entityQuery */
      $this->sourceEntity = $entityQuery->getEntity($this->source->entity_type, $this->source->entity_id);
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
    /** @var \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $source_entity */
    $source_entity = $this->getSourceEntity();
    if ($source_entity && $source_entity->id() == $base_object->getSourceId()) {
      return TRUE;
    }
    $parent_base_object = $base_object instanceof BaseObjectChildInterface ? $base_object->getParentBaseObject() : NULL;
    if ($source_entity && $parent_base_object && $source_entity->id() == $parent_base_object->getSourceId()) {
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
    return $this->original_fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalFieldTypes() {
    return $this->original_field_types;
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
    return $this->fields;
  }

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getGoalMetricFields() {
    $measurements = $this->measurement_fields;
    return array_filter($this->fields, function ($field) use ($measurements) {
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
   * Get the type of unit for an attachment.
   *
   * @return string|null
   *   The unit type as a string.
   */
  public function getUnitType() {
    return $this->unit ? $this->unit->type : NULL;
  }

  /**
   * Get the label of the unit for an attachment.
   *
   * @return string|null
   *   The unit label as a string.
   */
  public function getUnitLabel($langcode = NULL) {
    if ($langcode && !empty($this->unit->locale[$langcode])) {
      return $this->unit->locale[$langcode];
    }
    return $this->unit->label ?? NULL;
  }

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype
   *   The attachment prototype object.
   */
  public function getPrototype() {
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
    $attachment_data = $this->getRawData();
    $plan_id = NULL;
    if (!empty($attachment_data->objectType) && is_string($attachment_data->objectType) && $attachment_data->objectType == 'plan') {
      $plan_id = $attachment_data->objectId;
    }
    elseif (!empty($attachment_data->planId)) {
      $plan_id = $attachment_data->planId;
    }
    elseif (!empty($attachment_data->attachmentPrototype->planId)) {
      $plan_id = $attachment_data->attachmentPrototype->planId;
    }
    elseif (!empty($attachment_data->measurements) && !empty($attachment_data->measurements[0]?->attachment?->planId)) {
      $plan_id = $attachment_data->measurements[0]?->attachment?->planId;
    }
    elseif (!empty($attachment_data->objectType) && is_string($attachment_data->objectType) && $attachment_data->objectType == 'governingEntities') {
      $object_id = $attachment_data->objectId;
      $entity = BaseObjectHelper::getBaseObjectFromOriginalId($object_id, 'governing_entity');
      $plan_id = $entity instanceof GoverningEntity ? $entity->getPlan()?->id() : NULL;
    }
    return $plan_id;
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
    foreach ($disaggregated_data as $metric_item) {
      if ($this->metricItemIsEmpty($metric_item)) {
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
    if (!array_key_exists('locations', $metric_item) || empty($metric_item['locations'])) {
      return TRUE;
    }
    return empty(array_filter($metric_item['locations'], function ($location) {
      return !empty($location['total']);
    }));
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
   * @return array
   *   A processed array of disaggregated data.
   */
  public function getDisaggregatedData($reporting_period = 'latest', $filter_empty_locations = FALSE, $filter_empty_categories = FALSE, $ignore_missing_location_ids = FALSE) {
    $this->assureDisaggregatedData();
    $attachment_data = $this->getRawData();

    // First check if we have already processed this data.
    $cache_key = $this->getCacheKey([
      'attachment_id' => $attachment_data->id,
      'reporting_period' => $reporting_period,
      'filter_empty_locations' => intval($filter_empty_locations),
      'filter_empty_categories' => intval($filter_empty_categories),
      'ignore_missing_location_ids' => intval($ignore_missing_location_ids),
      'updated' => $attachment_data->attachmentVersion->updatedAt,
      // This hash is here to get a better chance of capturing differences of
      // data for the same attachment id, like when the attachment data is
      // retrieved by an anonymous user vs a logged-in user, where both might
      // see different data, depending on their access level.
      'hash' => md5(Yaml::encode(ArrayHelper::mapObjectsToString([$attachment_data->attachmentVersion?->value?->metrics?->values?->totals]))),
    ]);

    $cached_data = $this->cache($cache_key);
    if ($cached_data !== NULL) {
      return $cached_data;
    }

    $cache_tags = [];

    // No, so we need to do it now. Extract the base metrics and base data.
    $base_metrics = $this->getBaseMetricTotals($reporting_period);
    $base_data = $this->getBaseData($reporting_period);
    if (empty($base_data)) {
      return [];
    }

    // Remove the first row (full country totals, always empty for some reason
    // and not part of the locations array anyway).
    array_shift($base_data->dataMatrix);

    // Check if it's worth going on, no locations or no categories means we
    // won't have anything meaningful to display, so we bail out.
    $disaggregated_data = [];
    if (empty($base_data->locations) && empty($base_data->categories)) {
      return $this->cache($cache_key, FALSE);
    }

    $locations = $this->getLocations($base_data, $ignore_missing_location_ids);

    // Shortcut to the data matrix and the categories.
    $data_matrix = $base_data->dataMatrix;
    $categories = $base_data->categories;

    // Now $locations contains all locations that make up the data matrix,
    // containing map coordinates where available.
    // Now go through the properties and create a simplified version of the data
    // matrix.
    foreach ($base_metrics as $index => $property) {
      $disaggregated_data[$index] = [
        'metric' => $property,
        'unit_type' => $this->getUnitType(),
        'locations' => [],
        'category_totals' => [],
        'is_measurement' => $this->isMeasurementField($property->name->en),
      ];

      foreach ($locations as $location_index => $location) {
        if (empty($location->map_data)) {
          continue;
        }
        $cache_tags = Cache::mergeTags($cache_tags, $location->cache_meta_data->getCacheTags());

        $disaggregated_data[$index]['locations'][$location_index] = (array) $location;
        $disaggregated_data[$index]['locations'][$location_index] += [
          'object_id' => $location->id,
          'categories' => [],
          'total' => NULL,
        ];
        $location_data_matrix = $data_matrix[$location_index];

        $offset = 0;
        foreach ($categories as $category) {
          $disaggregated_data[$index]['locations'][$location_index]['categories'][$category->label] = [
            'name' => $category->name,
            'data' => array_key_exists($offset + $index, $location_data_matrix) ? $location_data_matrix[$offset + $index] : NULL,
          ];
          $offset += count($base_metrics);
        }

        $total = array_key_exists($offset + $index, $location_data_matrix) ? $location_data_matrix[$offset + $index] : NULL;
        $total = $total !== NULL ? intval($total) : NULL;
        $disaggregated_data[$index]['locations'][$location_index]['total'] = $total;
        $disaggregated_data[$index]['locations'][$location_index]['map_data']['total'] = $total;
      }

      foreach ($categories as $category_index => $category) {
        $category_index = count($base_metrics) * $category_index + $index;
        $first_row = reset($data_matrix);
        $category_total = array_key_exists($category_index, $first_row) ? $first_row[$category_index] : NULL;
        $category_total = $category_total !== NULL ? intval($category_total) : NULL;
        if (!empty($category_total)) {
          $disaggregated_data[$index]['category_totals'][$category->name] = $category_total;
        }
      }
    }

    if ($filter_empty_locations) {
      // Filter data points that are not relevant, which means all locations
      // where the categories have no data and where there is no total for the
      // location.
      foreach ($disaggregated_data as $metric_index => $metric_items) {
        foreach ($metric_items['locations'] as $location_index => $location_item) {
          $has_data = count(array_filter($location_item['categories'], function ($category) {
            return !empty($category['data']);
          })) > 0;
          if (!$has_data && empty($location_item['total'])) {
            unset($disaggregated_data[$metric_index]['locations'][$location_index]);
          }
        }
      }
    }

    if ($filter_empty_categories) {
      // Filter data points that are not relevant, which means all categories
      // which don't have data for any of the locations.
      foreach ($disaggregated_data as $metric_index => $metric_items) {
        if (empty($metric_items['locations'])) {
          // No locations, so there aren't any categories either.
          continue;
        }

        $empty_categories = [];
        foreach ($categories as $category) {
          $empty_categories[$category->label] = TRUE;
        }

        foreach ($metric_items['locations'] as $location_index => $location_item) {
          foreach ($location_item['categories'] as $category_key => $category) {
            if ($empty_categories[$category_key] === TRUE && !empty($category['data'])) {
              $empty_categories[$category_key] = FALSE;
            }
          }
        }

        foreach ($metric_items['locations'] as $location_index => $location_item) {
          foreach (array_keys(array_filter($empty_categories)) as $empty_category) {
            unset($disaggregated_data[$metric_index]['locations'][$location_index]['categories'][$empty_category]);
          }
        }
      }
    }

    $this->setCacheTags($cache_tags);
    return $this->cache($cache_key, $disaggregated_data, FALSE, NULL, $cache_tags);
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
    $locations = $disaggregated_data[$property_index]['locations'];
    $first_location = reset($locations);
    if (empty($first_location['categories'])) {
      return FALSE;
    }
    return array_map(function ($item) {
      return $item['name'];
    }, $first_location['categories']);
  }

  /**
   * Assure that the disaggregated data for an attachment has been fetched.
   */
  private function assureDisaggregatedData() {
    $attachment_data = $this->getRawData();
    if (property_exists($attachment_data->attachmentVersion->value->metrics->values, 'disaggregated')) {
      // Nothing to do.
      return;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $attachment_query */
    $attachment_query = $this->getEndpointQueryManager()->createInstance('attachment_query');
    $attachment_data = $attachment_query->getAttachmentDataWithDisaggregatedData($this->id);
    if (!$attachment_data) {
      return;
    }
    $this->setRawData($attachment_data);
    $this->updateMap();
  }

  /**
   * Retrieve the base metrics of an attachment.
   *
   * If measurements are available, those will be used, otherwhise the normal
   * metrics will be used.
   */
  private function getBaseMetricTotals($reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    if ($measurement && !empty($measurement->totals)) {
      $base_metrics = $measurement->totals;
    }
    else {
      $base_metrics = $this->totals;
    }
    return $base_metrics;
  }

  /**
   * Retrieve the base data of an attachment.
   *
   * If measurements are available, those will be used, otherwhise the normal
   * metrics data will be used.
   */
  private function getBaseData($reporting_period = 'latest') {
    $measurement = $this->getMeasurementByReportingPeriod($reporting_period);
    if ($measurement && !empty($measurement->disaggregated)) {
      $base_data = $measurement->disaggregated;
    }
    elseif (!empty($this->disaggregated)) {
      $base_data = $this->disaggregated;
    }
    else {
      return NULL;
    }
    return clone $base_data;
  }

  /**
   * Extract the country out of an array of locations.
   */
  private function getMainCountryFromLocations($locations) {
    // This gives either an array with a single location that is the main
    // country, or in case of some plans, like oPt 2017, which do not report the
    // relationship between the countries properly, this gives the original
    // locations array. The latter case is mitigated by the fact, that we assume
    // that the first element is the main country.
    $country_candidates = array_filter($locations, function ($location) {
      return empty($location->parent) && !empty($location->id);
    });
    if (empty($country_candidates)) {
      return NULL;
    }
    return reset($country_candidates);
  }

  /**
   * Get the main country from the plan id.
   *
   * @param int $plan_id
   *   The plan id for which to retrieve the country.
   *
   * @return object
   *   An object with 2 keys: 'id' and 'name'.
   */
  private function getMainCountryFromPlanId($plan_id) {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanLocationsQuery $plan_locations_query */
    $plan_locations_query = $this->getEndpointQueryManager()->createInstance('plan_locations_query');
    $plan_locations_query->setPlaceholder('plan_id', $plan_id);
    $country = $plan_locations_query->getCountry();
    return (object) [
      'id' => $country->id,
      'name' => $country->name,
    ];
  }

  /**
   * Get the locations for the current attachment.
   *
   * @param object $base_data
   *   The base data of the attachment.
   * @param bool $ignore_missing_location_ids
   *   Whether to ignore locations with missing ids.
   *
   * @return array
   *   An array of location objects.
   */
  private function getLocations($base_data, $ignore_missing_location_ids) {

    // We extract the country from the locations array.
    $country = $this->getMainCountryFromLocations($base_data->locations);
    if (!$country && !empty($this->getPlanId())) {
      // The disaggregation data doesn't seem to include the main country in the
      // locations. Let's try to get it from the plan id.
      $country = $this->getMainCountryFromPlanId($this->getPlanId());
    }

    // Then we remove the country from the locations array and create an array
    // of location spot candidates.
    $locations = array_filter($base_data->locations, function ($location) use ($country, $ignore_missing_location_ids) {
      return (!empty($location->id) || $ignore_missing_location_ids) && (($location->id ?? NULL) != $country->id);
    });
    $location_ids = array_map(function ($location) {
      return $location->id;
    }, $locations);

    /** @var \Drupal\ghi_base_objects\Plugin\EndpointQuery\LocationsQuery $locations_query */
    $locations_query = $this->getEndpointQueryManager()->createInstance('locations_query');

    // See until which level of detail we should go for the attachment. This is
    // stored as a configuration option on the plan base object, so let's look
    // that up.
    $plan_object = $this->getPlanObject();
    $max_level = $plan_object ? $plan_object->getMaxAdminLevel() : NULL;

    // Then we get the coordinates for all locations that the API knows for this
    // country. The coordinates are keyed by the location id.
    /** @var \Drupal\ghi_base_objects\ApiObjects\Location[] $country_locations */
    $country_locations = $country && $locations_query ? $locations_query->getCountryLocations($country->id, $max_level, $location_ids) : [];

    foreach ($locations as $location_key => $location) {
      $locations[$location_key]->country_id = $country->id;
      if (empty($location->id)) {
        continue;
      }
      $_location = !empty($country_locations[$location->id]) ? $country_locations[$location->id] : NULL;
      if (empty($_location)) {
        continue;
      }
      $locations[$location_key]->map_data = $_location->toArray();
      $locations[$location_key]->map_data['object_id'] = $location->id;
      $locations[$location_key]->map_data['total'] = 0;
      $locations[$location_key]->cache_meta_data = CacheableMetadata::createFromObject($_location);

      // @see https://humanitarian.atlassian.net/browse/HPC-9838?focusedCommentId=201540
      $locations[$location_key]->name = $locations[$location_key]->map_data['location_name'];
    }
    return $locations;
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @return array
   *   Array with values for each metric and measurement data point, according
   *   to the prototype.
   */
  protected function extractValues() {
    $prototype = $this->getPrototypeData();
    $metrics = $this->getMetrics();

    // Then get the measure fields.
    $measure_fields = $metrics?->measureFields ?? NULL;
    $calculated_fields = $metrics?->calculatedFields ?? NULL;

    // Work around an issue with the API format for this.
    $calculated_fields = is_array($calculated_fields) ? $calculated_fields : [$calculated_fields];

    // And merge metrics and measurements together.
    return array_pad(array_merge(
      array_map(function ($item) {
        return $item->value ?? NULL;
      }, $metrics?->values?->totals ?? []),
      $measure_fields ? array_map(function ($item) {
        return $item->value ?? NULL;
      }, $measure_fields) : [],
      $calculated_fields ? array_map(function ($item) {
        return $item->value ?? NULL;
      }, $calculated_fields) : [],
    ), count($prototype->fields), NULL);
  }

  /**
   * Get the metrics from the given attachment.
   *
   * This fetches either the metrics from the attachment version, or from a
   * measurement if a published one is already present.
   *
   * @return object|null
   *   A metric object or NULL.
   */
  protected function getMetrics() {
    $attachment = $this->getRawData();
    if (!$attachment || !is_object($attachment)) {
      return NULL;
    }
    // Get the metrics from the attachment version by default.
    $metrics = $attachment->attachmentVersion?->value?->metrics ?? NULL;
    // If there are measurements, look at the most recent one and get the
    // metrics from there.
    $measurement = self::getCurrentMeasurement();
    if ($measurement) {
      $metrics = $measurement->metrics;
    }
    return $metrics;
  }

  /**
   * Get the all measurements.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Measurements\Measurement[]|null
   *   An array of measurement objects or NULL.
   */
  public function getMeasurements() {
    $attachment = $this->getRawData();
    if (!$attachment || !is_object($attachment)) {
      return NULL;
    }
    $measurements = property_exists($attachment, 'measurements') ? $attachment->measurements : NULL;
    if ($measurements === NULL && $measurements_query = $this->getEndpointQueryManager()->createInstance('measurement_query')) {
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\MeasurementQuery $measurements_query */
      $measurements = $measurements_query->getUnprocessedMeasurements($this, TRUE);
      $attachment->measurements = $measurements;
      $this->setRawData($attachment);
      $this->updateMap();
    }

    if (empty($measurements)) {
      return NULL;
    }
    ArrayHelper::sortObjectsByNumericProperty($measurements, 'planReportingPeriodId', EndpointQuery::SORT_DESC);
    return array_map(function ($measurement) {
      return new Measurement($measurement);
    }, array_values($measurements));
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
    $latest_published_period_id = $this->getLatestPublishedReportingPeriod($this->getPlanId());
    if (!$latest_published_period_id) {
      return NULL;
    }
    $measurements = array_filter($measurements, function ($measurement) use ($latest_published_period_id) {
      return $measurement->reporting_period == $latest_published_period_id;
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
   * Extract prototype information from an attachment.
   *
   * Not all endpoints include the prototype in the response, which is why we
   * provide a work around to infer the prototype from the attachment object
   * itself. This does not contain all the prototype information. Calling
   * classes should assure that they use the correct endpoint if full prototype
   * data is required.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype
   *   An attachment prototype object.
   *
   * @throws \Exception
   *   If the prototype cannot be inferred.
   */
  protected function getPrototypeData() {
    $attachment = $this->getRawData();
    if (property_exists($attachment, 'attachmentPrototype')) {
      $prototype = new AttachmentPrototype($attachment->attachmentPrototype);
    }
    else {
      $prototype = self::fetchPrototypeForAttachment($attachment);
    }

    if (!$prototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $attachment->id));
    }

    return $prototype;
  }

  /**
   * Get a single specified reporting period object.
   *
   * @param int $period_id
   *   The reporting period id.
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
   * @param string $monitoring_period
   *   A monitoring period identifier or 'latest'.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of reporting period objects.
   */
  public function getReportingPeriods(?array $reporting_periods = NULL, $monitoring_period = 'latest') {
    if ($reporting_periods === NULL) {
      $reporting_periods = $this->getPlanReportingPeriods($this->getPlanId(), TRUE);
    }
    if (is_array($reporting_periods) && $monitoring_period != 'latest') {
      while (!empty($reporting_periods) && array_key_last($reporting_periods) != $monitoring_period) {
        array_pop($reporting_periods);
      }
    }
    return $reporting_periods;
  }

  /**
   * Fetch prototype data from the API.
   *
   * @param object $attachment
   *   The attachment object from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   An attachment prototype object.
   */
  protected function fetchPrototypeForAttachment($attachment) {
    // First see if we can extract the attachment from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanAttachmentPrototypeQuery $query_handler */
    $query_handler = $this->getEndpointQueryManager()->createInstance('plan_attachment_prototype_query');
    if (!$query_handler) {
      return NULL;
    }
    if ($prototype = $query_handler->getPrototypeByPlanAndId($attachment->planId, $attachment->attachmentPrototypeId)) {
      return $prototype;
    }

    // If that didn't work, we query the full attachment data to extract the
    // prototype from there.
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query_handler */
    $query_handler = $this->getEndpointQueryManager()->createInstance('attachment_query');
    if (!$query_handler) {
      return NULL;
    }
    return $query_handler->getPrototype($attachment->id);
  }

  /**
   * Fetch the reporting period for the given attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  protected function fetchReportingPeriodForAttachment() {
    $plan_id = $this->getPlanId();
    if (!$plan_id) {
      return NULL;
    }
    $measurement = $this->getCurrentMeasurement();
    if (!$measurement) {
      return NULL;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanReportingPeriodsQuery $planReportingPeriodsQuery */
    $planReportingPeriodsQuery = $this->getEndpointQueryManager()->createInstance('plan_reporting_periods_query');
    if (!$planReportingPeriodsQuery) {
      return NULL;
    }
    $planReportingPeriodsQuery->setPlaceholder('plan_id', $plan_id);
    return $planReportingPeriodsQuery->getReportingPeriod($measurement->getReportingPeriodId());
  }

  /**
   * Get the endpoint query manager.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
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
    if ($monitoring_period) {
      $value = $this->getMeasurementMetricValue($data_point_index, $monitoring_period);
    }
    if (!$monitoring_period || (!$value && !$this->isMeasurementIndex($data_point_index))) {
      // If a monitoring period has been specified but there is no value,
      // that's either because a measurement is not yet available or because
      // there is an issue with the data in RPM, where the metric values
      // haven't been copied over to the measurements. That last issue is why
      // we do this check.
      $value = $this->values[$data_point_index] ?? NULL;
    }

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
  protected function getTooltip($conf) {
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
      return [
        '#markup' => $this->isPendingDataEntry() ? $this->t('Pending', [], $t_options) : $this->t('No data', [], $t_options),
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
