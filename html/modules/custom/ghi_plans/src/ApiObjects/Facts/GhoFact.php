<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;

/**
 * Represents a GHO fact with its plan, period, location and sector references.
 */
class GhoFact extends ApiObjectBase {

  /**
   * The revision state used for currently valid GHO values.
   */
  public const GHO_REVISION_STATE_ID = 3;

  /**
   * GraphQL fields required by plan- and sector-level consumers.
   */
  public const GRAPHQL_ITEMS = [
    'Id',
    'OperationId',
    'PlanId',
    'PeriodId',
    'LocationId',
    'SectorId',
    'MetricTypeId',
    'AggregationTypeId',
    'RevisionStateId',
    'AsOfDate',
    'ValueNum',
    'Description',
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    'metricType' => MetricType::GRAPHQL_ITEMS,
    'sector' => [
      'Id',
      'Name',
      'SectorType',
      'EntityTypeId',
      'ReferenceCode',
    ],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * The plan ID.
   */
  protected int $planId;

  /**
   * The period ID.
   */
  protected ?int $periodId;

  /**
   * The location ID.
   */
  protected ?int $locationId;

  /**
   * The sector ID.
   */
  protected ?int $sectorId;

  /**
   * The metric type ID.
   */
  protected int $metricTypeId;

  /**
   * The aggregation type ID.
   */
  protected ?int $aggregationTypeId;

  /**
   * The revision state ID.
   */
  protected ?int $revisionStateId;

  /**
   * The as-of date used to order fact revisions.
   */
  protected ?string $asOfDate;

  /**
   * The numeric metric value.
   */
  protected ?float $value;

  /**
   * The fact description.
   */
  protected ?string $description;

  /**
   * The metric type.
   */
  private ?MetricType $metricType;

  /**
   * The referenced sector, including its global entity classification.
   */
  private ?object $sector;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->planId = (int) $data->PlanId;
    $this->periodId = isset($data->PeriodId) ? (int) $data->PeriodId : NULL;
    $this->locationId = isset($data->LocationId) ? (int) $data->LocationId : NULL;
    $this->sectorId = isset($data->SectorId) ? (int) $data->SectorId : NULL;
    $this->metricTypeId = (int) $data->MetricTypeId;
    $this->aggregationTypeId = isset($data->AggregationTypeId) ? (int) $data->AggregationTypeId : NULL;
    $this->revisionStateId = isset($data->RevisionStateId) ? (int) $data->RevisionStateId : NULL;
    $this->asOfDate = $data->AsOfDate ?? NULL;
    $this->value = isset($data->ValueNum) ? (float) $data->ValueNum : NULL;
    $this->description = $data->Description ?? NULL;
    $this->metricType = isset($data->metricType) ? new MetricType($data->metricType) : NULL;
    $this->sector = $data->sector ?? NULL;
  }

  /**
   * Gets the plan ID.
   */
  public function getPlanId(): int {
    return $this->planId;
  }

  /**
   * Gets the period ID.
   */
  public function getPeriodId(): ?int {
    return $this->periodId;
  }

  /**
   * Gets the location ID.
   */
  public function getLocationId(): ?int {
    return $this->locationId;
  }

  /**
   * Gets the sector ID.
   */
  public function getSectorId(): ?int {
    return $this->sectorId;
  }

  /**
   * Gets the metric type ID.
   */
  public function getMetricTypeId(): int {
    return $this->metricTypeId;
  }

  /**
   * Gets the metric type.
   */
  public function getMetricType(): ?MetricType {
    return $this->metricType;
  }

  /**
   * Gets the normalized metric machine name.
   */
  public function getMetricName(): ?string {
    return $this->metricType?->getMachineName();
  }

  /**
   * Gets the aggregation type ID.
   */
  public function getAggregationTypeId(): ?int {
    return $this->aggregationTypeId;
  }

  /**
   * Gets the revision state ID.
   */
  public function getRevisionStateId(): ?int {
    return $this->revisionStateId;
  }

  /**
   * Checks whether this fact belongs to the GHO revision.
   */
  public function isGhoRevision(): bool {
    return $this->revisionStateId === self::GHO_REVISION_STATE_ID;
  }

  /**
   * Gets the as-of date.
   */
  public function getAsOfDate(): ?string {
    return $this->asOfDate;
  }

  /**
   * Gets the numeric value.
   */
  public function getValue(): ?float {
    return $this->value;
  }

  /**
   * Gets the optional description.
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * Gets the referenced sector name.
   */
  public function getSectorName(): ?string {
    return $this->sector?->Name ?? NULL;
  }

  /**
   * Gets the sector's Global Cluster or Global Sector entity type ID.
   */
  public function getSectorEntityTypeId(): ?int {
    return isset($this->sector?->EntityTypeId) ? (int) $this->sector->EntityTypeId : NULL;
  }

  /**
   * Gets the referenced sector code.
   */
  public function getSectorReferenceCode(): ?string {
    return $this->sector?->ReferenceCode ?? NULL;
  }

}
