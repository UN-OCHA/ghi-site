<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

/**
 * Abstraction for API measurement fact objects.
 */
class MeasurementFact extends FactBase {

  /**
   * The measurement id.
   *
   * @var int
   */
  protected int $measurementId;

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
    'DerivedMetricSource',
    'IsTotal',
    'ValueNum',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->measurementId = $data->MeasurementId;
  }

}
