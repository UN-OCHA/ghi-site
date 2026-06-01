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
   * The source of a calculated field.
   *
   * @var string|null
   */
  protected ?string $calculatedFieldSource;

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
  public function __construct(object $data) {
    parent::__construct($data);
    $this->measurementId = $data->MeasurementId;

    // This looks wrong, but current storage in the datastore has description
    // for measurement facts only if the metric represents a calculated metric.
    $this->calculatedFieldSource = $data->Description;
  }

}
