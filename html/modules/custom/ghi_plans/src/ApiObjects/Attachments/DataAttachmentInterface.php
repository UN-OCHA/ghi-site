<?php

namespace Drupal\ghi_plans\ApiObjects\Attachments;

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
  public function getCustomId();

  /**
   * Get the type of attachment.
   *
   * @return string
   *   The type as string.
   */
  public function getType();

  /**
   * Get the source entity.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityType();

  /**
   * Get the source entity.
   *
   * @return string|null
   *   The source entity type.
   */
  public function getSourceEntityId();

  /**
   * Get the source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface|null
   *   The entity object.
   */
  public function getSourceEntity();

  /**
   * Get the current monitoring period for this attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   A reporting period object or NULL.
   */
  public function getCurrentMonitoringPeriod();

  /**
   * Get the fields used in an attachment.
   *
   * @return object[]
   *   An array of field objects as provided by the API.
   */
  public function getOriginalFields();

  /**
   * Get the field types used in an attachment.
   *
   * @return string[]
   *   An array of field types as strings.
   */
  public function getOriginalFieldTypes();

  /**
   * Get a data field by type.
   *
   * @param string $type
   *   The type of data point to retrieve.
   *
   * @return object
   *   The field as retrieved from the API.
   */
  public function getFieldByType($type);

  /**
   * Get a field by it's index in the field list.
   *
   * @param int $index
   *   The index of the field to fetch.
   *
   * @return object|null
   *   The field if found.
   */
  public function getFieldByIndex($index);

  /**
   * Get the metric fields.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMetricFields();

  /**
   * Get the fields that represent goal metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getGoalMetricFields();

  /**
   * Get the fields that represent measurement metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getMeasurementMetricFields();

  /**
   * Get the fields that represent calculated metrics.
   *
   * @return string[]
   *   An array of metric names.
   */
  public function getCalculatedMetricFields();

  /**
   * Get the source property for the calculated field.
   *
   * @param int $index
   *   The index of the data point in the list of all fields.
   *
   * @return string|null
   *   The source field type of the calculated field.
   */
  public function getSourceTypeForCalculatedField($index);

  /**
   * Get the type of unit for an attachment.
   *
   * @return string|null
   *   The unit type as a string.
   */
  public function getUnitType();

  /**
   * Get the label of the unit for an attachment.
   *
   * @return string|null
   *   The unit label as a string.
   */
  public function getUnitLabel($langcode = NULL);

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype
   *   The attachment prototype object.
   */
  public function getPrototype();

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isMeasurementIndex($index);

  /**
   * Check if the given field label represens a measurement metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a measurement, FALSE otherwise.
   */
  public function isMeasurementField($field_label);

  /**
   * Check if the given data point index represens a measurement metric.
   *
   * @param int $index
   *   The index of the data point to check.
   *
   * @return bool
   *   TRUE if the index represents a measurement, FALSE otherwise.
   */
  public function isCalculatedIndex($index);

  /**
   * Check if the given field label represens a calculated metric.
   *
   * @param string $field_label
   *   The field label.
   *
   * @return bool
   *   TRUE if the field is a calculated metric, FALSE otherwise.
   */
  public function isCalculatedField($field_label);

}
