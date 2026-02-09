<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Traits\DateTimeTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction for API measurement fact objects.
 */
class MeasurementFact extends ApiObjectBase {

  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;
  use DateTimeTrait;
  use PlanQueryTrait;

  /**
   * The metric type used by the fact.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\MetricType
   */
  private $metric = NULL;

  /**
   * Define the fact items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'MeasurementId',
    'MetricTypeId',
    'PeriodId',
    'LocationId',
    'GenderId',
    'AgeGroupId',
    'PopulationStatusId',
    'SettlementTypeId',
    'DisabilityStatusId',
    'HealthInterventionCategoryId',
    'MaternalStatusId',
    'DisaggregationCategoryOtherId',
    'DeliveryModalityId',
    'CalcMethodId',
    'CustomMetricName',
    'Description',
    'IsTotal',
    'ValueNum',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $fact = $this->getRawData();
    return (object) [
      'id' => $fact->Id,
      'measurement_id' => $fact->MeasurementId,
      'metric_id' => $fact->MetricTypeId,
      'period_id' => $fact->PeriodId,
      'location_id' => $fact->LocationId,
      'gender_id' => $fact->GenderId,
      'age_group_id' => $fact->AgeGroupId,
      'population_status_id' => $fact->PopulationStatusId,
      'settlement_type_id' => $fact->SettlementTypeId,
      'disability_status_id' => $fact->DisabilityStatusId,
      'health_intervention_category_id' => $fact->HealthInterventionCategoryId,
      'delivery_modality_id' => $fact->DeliveryModalityId,
      'calc_method_id' => $fact->CalcMethodId,
      'custom_metric_name' => $fact->CustomMetricName,
      'description' => $fact->Description,
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
    if ($this->metric) {
      return $this->metric;
    }
    $this->metric = $this->getEntityTypeQuery()?->getMetricType($this->map->metric_id) ?? NULL;
    return $this->metric;
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

  /**
   * Get the location id.
   *
   * @return int
   *   The location id of the fact.
   */
  public function getLocationId(): ?int {
    return $this->map->location_id ?? NULL;
  }

  /**
   * Get the category ideas that a fact applies to.
   *
   * @return int[]
   *   An array of category ids.
   */
  public function getCategoryIds() {
    $category_properties = [
      'gender_id',
      'age_group_id',
      'population_status_id',
      'settlement_type_id',
      'disability_status_id',
      'health_intervention_category_id',
      'delivery_modality_id',
    ];
    $categories = [];
    foreach ($category_properties as $property_name) {
      if (empty($this->map->$property_name)) {
        continue;
      }
      $categories[$property_name] = $this->map->$property_name;
    }
    return $categories;
  }

}
