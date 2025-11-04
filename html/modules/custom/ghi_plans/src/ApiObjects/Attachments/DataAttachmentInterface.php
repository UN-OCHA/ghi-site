<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Interface for API data attachment objects.
 */
interface DataAttachmentInterface extends AttachmentInterface {

  /**
   * Get the custom id of the attachment.
   *
   * @return string
   *   The custom id of the attachment.
   */
  public function getCustomId(): string;

  /**
   * Get the custom id with the ref code of the attachment.
   *
   * @return string
   *   The custom id of the attachment.
   */
  public function getCustomIdWithRefCode(): string;

  /**
   * Get the type of attachment.
   *
   * @return string
   *   The type as string.
   */
  public function getType(): string;

  /**
   * Get the source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface|null
   *   The entity object.
   */
  public function getSourceEntity(): ?PlanEntityInterface;

  /**
   * See if the attachment belongs to the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object to check.
   *
   * @return bool
   *   TRUE if the attachment belongs to the base object, FALSE otherwise.
   */
  public function belongsToBaseObject(BaseObjectInterface $base_object): bool;

  /**
   * Get the current monitoring period for this attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  public function getCurrentMonitoringPeriod(): ?PlanReportingPeriod;

  /**
   * Get the fields used in an attachment.
   *
   * @return object[]
   *   An array of field objects as provided by the API.
   */
  public function getOriginalFields(): array;

  /**
   * Get the field types used in an attachment.
   *
   * @return string[]
   *   An array of field types as strings.
   */
  public function getOriginalFieldTypes(): array;

  /**
   * Get a data field by type.
   *
   * @param string $type
   *   The type of data point to retrieve.
   *
   * @return object|null
   *   The field as retrieved from the API.
   */
  public function getFieldByType($type): ?object;

  /**
   * Get a field by it's index in the field list.
   *
   * @param int $index
   *   The index of the field to fetch.
   *
   * @return object|null
   *   The field if found.
   */
  public function getFieldByIndex($index): ?object;

  /**
   * Get the metric fields.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMetricFields(): array;

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getGoalMetricFields(): array;

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementMetricFields(): array;

  /**
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedMetricFields(): array;

  /**
   * Get the source property for the calculated field.
   *
   * @param int $index
   *   The index of the data point in the list of all fields.
   *
   * @return string|null
   *   The source field type of the calculated field.
   */
  public function getSourceTypeForCalculatedField($index): ?string;

  /**
   * Get the type of unit for an attachment.
   *
   * @return string|null
   *   The unit type as a string.
   */
  public function getUnitType(): ?string;

  /**
   * Get the label of the unit for an attachment.
   *
   * @return string|null
   *   The unit label as a string.
   */
  public function getUnitLabel($langcode = NULL): ?string;

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype|null
   *   The attachment prototype object.
   */
  public function getPrototype(): ?AttachmentPrototype;

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isMeasurementIndex($index): bool;

  /**
   * Check if the given field label represens a measurement metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a measurement, FALSE otherwise.
   */
  public function isMeasurementField($field_label): bool;

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isCalculatedIndex($index): bool;

  /**
   * Check if the given field label represens a calculated metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a calculated metric, FALSE otherwise.
   */
  public function isCalculatedField($field_label): bool;

  /**
   * Check if the given data point index represents a calculated metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a calculated metric, FALSE otherwise.
   */
  public function isCalculatedMeasurementIndex($index): bool;

  /**
   * See if data entry is still pending for this attachment.
   *
   * If there is no published reporting period yet, data entry is pending.
   * See https://humanitarian.atlassian.net/browse/HPC-5949
   *
   * @return bool
   *   TRUE if data entry is still pending, FALSE otherwise.
   */
  public function isPendingDataEntry(): bool;

  /**
   * Check if the given value should be considered NULL.
   *
   * @param mixed $value
   *   The value to check.
   *
   * @return bool
   *   TRUE if the value should be considered NULL, FALSE otherwise.
   */
  public function isNullValue($value): bool;

  /**
   * Extract the plan id from an attachment object.
   *
   * @return int|null
   *   The plan ID if any can be found.
   */
  public function getPlanId();

  /**
   * Get the plan object for this attachment.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan base object or NULL.
   */
  public function getPlanObject(): ?Plan;

  /**
   * Get the plan language.
   *
   * @return string|null
   *   The plan language code as a string or NULL.
   */
  public function getPlanLanguage(): ?string;

  /**
   * See if the API thinks that this attachment can have disaggregated data.
   *
   * @return bool
   *   TRUE if disaggregated data can be fetched, FALSE otherwise.
   */
  public function hasDisaggregatedData(): bool;

  /**
   * See if the attachment can be mapped for the given reporting period.
   *
   * @param int|string $reporting_period
   *   The reporting period id.
   *
   * @return bool
   *   TRUE if the attachment can be mapped, FALSE otherwise.
   */
  public function canBeMapped($reporting_period): bool;

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
  public function metricItemIsEmpty($metric_item): bool;

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
  public function getDisaggregatedDataMultiple(array $reporting_period_ids = [], $filter_empty_locations = FALSE, $filter_empty_categories = FALSE): array;

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
  public function getDisaggregatedData($reporting_period = 'latest', $filter_empty_locations = FALSE, $filter_empty_categories = FALSE, $ignore_missing_location_ids = FALSE) : array;

}
