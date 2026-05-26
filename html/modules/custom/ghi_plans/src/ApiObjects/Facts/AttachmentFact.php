<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

use Drupal\hpc_api\ApiObjects\Types\RevisionState;

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
   * The revision state id.
   *
   * @var int|null
   */
  protected ?int $revisionStateId;

  /**
   * The revision state used by the fact.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\RevisionState
   */
  private $revisionState = NULL;

  /**
   * Define the fact items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'AttachmentId',
    'MetricTypeId',
    'RevisionStateId',
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
    $this->description = $data->Description ?? NULL;
    $this->revisionStateId = $data->RevisionStateId ?? NULL;
  }

  /**
   * Get the revision state for this fact.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\RevisionState|null
   *   The revision state or NULL.
   */
  public function getRevisionState(): ?RevisionState {
    if ($this->revisionState) {
      return $this->revisionState;
    }
    $this->revisionState = $this->revisionStateId ? $this->getEntityTypeQuery()?->getRevisionState($this->revisionStateId) : NULL;
    return $this->revisionState;
  }

}
