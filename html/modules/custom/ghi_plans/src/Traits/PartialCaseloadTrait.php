<?php

namespace Drupal\ghi_plans\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Trait to help fullfill the DataAttachmentInterface for partial caseloads.
 *
 * E.g. used in \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewCaseload.
 */
trait PartialCaseloadTrait {

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomIdWithRefCode(): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return 'caseload';
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntity(): ?PlanEntityInterface {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function belongsToBaseObject(BaseObjectInterface $base_object): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrentMonitoringPeriod(): ?PlanReportingPeriod {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldByType($type): ?object {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldByIndex($index): ?object {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetricFields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getGoalMetricFields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getMeasurementMetricFields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCalculatedMetricFields(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceTypeForCalculatedField($index): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnitType(): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnitLabel($langcode = NULL): ?string {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?AttachmentPrototype {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isMeasurementIndex($index): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isMeasurementField($field_label): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isCalculatedIndex($index): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isCalculatedField($field_label): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isCalculatedMeasurementIndex($index): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isPendingDataEntry(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isNullValue($value): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanObject(): ?Plan {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanLanguage(): ?string {
    return 'en';
  }

  /**
   * {@inheritdoc}
   */
  public function hasDisaggregatedData(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function canBeMapped($reporting_period): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function metricItemIsEmpty($metric_item): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisaggregatedDataMultiple(array $reporting_period_ids = [], $filter_empty_locations = FALSE, $filter_empty_categories = FALSE): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDisaggregatedData($reporting_period = 'latest', $filter_empty_locations = FALSE, $filter_empty_categories = FALSE, $ignore_missing_location_ids = FALSE) : array {
    return [];
  }

}
