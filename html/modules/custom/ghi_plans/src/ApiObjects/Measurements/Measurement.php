<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\Core\Render\Markup;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for API measurement objects.
 */
class Measurement extends ApiObjectBase implements MeasurementInterface {

  use PlanQueryTrait;
  use DisaggregatedDataTrait;

  /**
   * The facts representing the totals.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact[]
   */
  protected $totals;

  /**
   * The disaggregated data.
   *
   * @var object
   */
  protected $disaggregated;

  /**
   * The attachment prototype.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype
   */
  protected $prototype;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'PlanId',
    'AttachmentId',
    'MeasurementPeriodId',
    'EntityId',
    'EntityTypeId',
    'EntityMainType',
    'MeasurementType',
    'UnitId',
    'CalculationMethod',
    'Description',
    'IsCommentPublic',
    'Comments',
    'VisibilityGroupId',
    'AttachmentPrototypeId',
    'RecordStatus',
    // 'ActiveUntil',
    // 'Source',
    // 'SourceId',
    // 'CreatedAt',
    'UpdatedAt',
    // 'IsLocked',
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    'measurementFact' => [
      'filter' => ['IsTotal' => TRUE, 'LocationId' => NULL],
      'items' => MeasurementFact::GRAPHQL_ITEMS,
    ],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $query = $this->getEntityTypeQuery();
    $measurement = $this->getRawData();
    $this->processTotals((array) ($measurement->measurementFact?->items ?? []));

    $processed = (object) [
      'id' => $measurement->Id,
      'type' => strtolower($measurement->MeasurementType),
      'source' => (object) [
        'entity_type' => PlanEntityHelper::checkObjectType($measurement->EntityMainType ?? NULL),
        'entity_id' => $measurement->EntityId ?? NULL,
        'plan_id' => $measurement->PlanId ?? NULL,
      ],
      'attachment_id' => $measurement->AttachmentId,
      'attachment_prototype_id' => $measurement->AttachmentPrototypeId,
      'custom_id' => $measurement->CustomReference ?? NULL,
      'composed_reference' => $measurement->ComposedReference ?? NULL,
      'description' => $measurement->Name ?? NULL,
      'comment' => !empty($measurement->IsCommentPublic) ? ($measurement->Comments ?? NULL) : NULL,
      'values' => $this->extractValues($this->totals),
      'unit' => ($measurement->UnitId ?? NULL) ? $query->getUnit($measurement->UnitId) : NULL,
      'monitoring_period' => $measurement->MeasurementPeriodId ?? NULL,
      'has_disaggregated_data' => TRUE,
      'calculation_method' => ($measurement->CalculationMethod ?? NULL),
    ];

    return $processed;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->map?->source?->plan_id ?? $this->getRawData()->PlanId;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityId() {
    return $this->source->entity_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getSourceEntityType() {
    return $this->source->entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentId() {
    return $this->map?->attachment_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportingPeriodId() {
    return $this->monitoring_period;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataPointValue($metric_type) {
    // @todo Add calculated fields.
    return $this->map->values[$metric_type] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment() {
    return !empty($this->comment) ? Markup::create($this->comment) : NULL;
  }

  /**
   * Process the totals.
   *
   * @param array $totals
   *   An array of raw fact objects.
   */
  protected function processTotals(array $totals) {
    $this->totals = array_map(fn ($item) => new MeasurementFact($item), $totals);
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[] $totals
   *   The totals to use for value extraction.
   *
   * @return array
   *   Array with values for each metric and measurement data point.
   */
  protected function extractValues(array $totals = []): array {
    $values = [];
    foreach ($totals as $item) {
      $values[$item->getMetric()->getMachineName()] = $item->getValue();
    }
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function getValues() {
    return $this->map->values;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotals(): array {
    return $this->totals;
  }

  /**
   * {@inheritdoc}
   */
  public function getDisaggregated(): object {
    if (!$this->disaggregated) {
      $measurement_query = $this->getMeasurementQuery();
      $disaggregated_data = $measurement_query?->getMeasurementDisaggregatedData($this->id());
      $facts = array_map(fn ($item) => new MeasurementFact($item), $disaggregated_data ?: []);
      $this->disaggregated = $this->buildDisaggregatedData($facts);
    }
    return $this->disaggregated;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?AttachmentPrototype {
    if ($this->prototype instanceof AttachmentPrototype) {
      return $this->prototype;
    }
    $measurement = $this->getRawData();

    // First see if we can extract the prototype from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    $attachment_prototype_query = $this->getAttachmentPrototypeQuery();
    if (!$attachment_prototype_query) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $measurement->Id));
    }
    $plan_id = $measurement->PlanId ?? NULL;
    $prototype_id = $measurement->AttachmentPrototypeId ?? NULL;
    if ($plan_id && $prototype_id && $prototype = $attachment_prototype_query->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    $prototype = $prototype_id ? $attachment_prototype_query->getPrototype($prototype_id) : NULL;
    if (!$prototype instanceof AttachmentPrototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $measurement->Id));
    }
    $this->prototype = $prototype;
    return $this->prototype;
  }

}
