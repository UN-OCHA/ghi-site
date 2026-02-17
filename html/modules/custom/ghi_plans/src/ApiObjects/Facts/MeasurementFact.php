<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

/**
 * Abstraction for API measurement fact objects.
 */
class MeasurementFact extends FactBase {

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

}
