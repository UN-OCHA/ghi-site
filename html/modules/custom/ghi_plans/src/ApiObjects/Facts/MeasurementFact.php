<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

/**
 * Abstraction for API measurement fact objects.
 */
class MeasurementFact extends FactBase {

  /**
   * Define the fact items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'MeasurementId',
    'AttachmentId',
    'MetricTypeId',
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
      'attachment_id' => $fact->MeasurementId,
      'metric_id' => $fact->MetricTypeId,
      'location_id' => $fact->LocationId,
      'gender_id' => $fact->GenderId,
      'age_group_id' => $fact->AgeGroupId,
      'population_status_id' => $fact->PopulationStatusId,
      'settlement_type_id' => $fact->SettlementTypeId,
      'disability_status_id' => $fact->DisabilityStatusId,
      'health_intervention_category_id' => $fact->HealthInterventionCategoryId,
      'delivery_modality_id' => $fact->DeliveryModalityId,
      'custom_metric_name' => $fact->CustomMetricName,
      'description' => $fact->Description,
      'is_total' => $fact->IsTotal,
      'value' => $fact->ValueNum,
    ];
  }

}
