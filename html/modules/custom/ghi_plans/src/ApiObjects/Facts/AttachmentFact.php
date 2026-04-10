<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

/**
 * Abstraction for attachment fact objects.
 */
class AttachmentFact extends FactBase {

  /**
   * The description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * Define the fact items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
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
    $this->description = $data->Description;
  }

}
