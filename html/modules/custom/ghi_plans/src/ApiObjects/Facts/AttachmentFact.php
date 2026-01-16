<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Traits\DateTimeTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction for API data attachment objects.
 */
class AttachmentFact extends ApiObjectBase {

  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;
  use DateTimeTrait;

  /**
   * Define a list of field types that should be considered cumulative reach.
   */
  const CUMULATIVE_REACH_FIELDS = [
    'cumulativeReach',
    'optionNonPlanCumulReach',
    'optionOverallCumulReach',
  ];

  const GRAPHQL_FACTS_ITEMS = '
    Id
    AttachmentId
    MetricTypeId
    PeriodId
    SectorId
    LocationId
    GenderId
    AgeGroupId
    PopulationStatusId
    SettlementTypeId
    DisabilityStatusId
    HealthInterventionCategoryId
    MaternalStatusId
    DisaggregationCategoryOtherId
    DeliveryModalityId
    CalcMethodId
    IsTotal
    ValueNum
    CustomMetricName
    EffectiveFrom
    EffectiveTo
    Description
    VisibilityGroupId
    Source
    SourceId
    CreatedAt
    UpdatedAt
    IsLocked
  ';

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $query = $this->getFabricQueryManager()->createInstance('plan');
    $fact = $this->getRawData();
    return (object) [
      'id' => $fact->Id,
      'attachment_id' => $fact->AttachmentId,
      'metric' => $query->getMetricType($fact->MetricTypeId),
      'is_total' => $fact->IsTotal,
      'value' => $fact->ValueNum,
    ];
  }

  /**
   * Get the metric type for this attachment fact.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType|null
   *   The metric type or NULL.
   */
  public function getMetric(): ?MetricType {
    return $this->map->metric ?? NULL;
  }

  /**
   * Whether this fact represents a total value.
   *
   * @return bool
   *   TRUE if the fact is a total, FALSE otherwise.
   */
  public function isTotal() {
    return $this->map->is_total;
  }

  /**
   * Get the value.
   *
   * @return float
   *   The value of the fact.
   */
  public function getValue() {
    return $this->map->value;
  }

}
