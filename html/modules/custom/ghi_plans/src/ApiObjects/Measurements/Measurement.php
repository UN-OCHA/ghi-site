<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\Core\Render\Markup;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction class for API measurement objects.
 */
class Measurement extends ApiObjectBase implements MeasurementInterface {

  use PlanQueryTrait;

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
    'CalculationMethodId',
    'Description',
    'VisibilityGroupId',
    'AttachmentPrototypeId',
    'RecordStatus',
    // 'ActiveUntil',
    // 'Source',
    // 'SourceId',
    // 'CreatedAt',
    'UpdatedAt',
    // 'IsLocked',
  ];

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $query = $this->getEntityTypeQuery();
    $measurement = $this->getRawData();

    $processed = (object) [
      'id' => $measurement->Id,
      'type' => strtolower($measurement->MeasurementType),
      'source' => (object) [
        'entity_type' => PlanEntityHelper::checkObjectType($measurement->EntityMainType ?? NULL),
        'entity_id' => $measurement->EntityId ?? NULL,
        'plan_id' => $measurement->PlanId ?? NULL,
      ],
      'attachment_prototype_id' => $measurement->AttachmentPrototypeId,
      'custom_id' => $measurement->CustomReference ?? NULL,
      'composed_reference' => $measurement->ComposedReference ?? NULL,
      'description' => $measurement->Name ?? NULL,
      'values' => $this->extractValues(),
      // 'disaggregated' => $this->extractDisaggregatedValues(),
      'unit' => ($measurement->UnitId ?? NULL) ? $query->getUnit($measurement->UnitId) : NULL,
      'monitoring_period' => $measurement->MeasurementPeriodId ?? NULL,
      'has_disaggregated_data' => !empty($measurement->HasDisaggregatedData),
      'calculation_method' => ($measurement->CalculationMethodId ?? NULL) ? $query->getCalculationMethod($measurement->CalculationMethodId)?->getName() : NULL,
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
  public function getReportingPeriodId() {
    return $this->monitoring_period;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataPointValue($index) {
    // @todo Add calculated fields.
    $metric_type = $this->getPrototype()->getOriginalFields()[$index]?->type;
    return $this->values[$metric_type] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getComment() {
    return !empty($this->comment) ? Markup::create($this->comment) : NULL;
  }

  /**
   * Extract the metric values from an attachment.
   *
   * @return array
   *   Array with values for each metric and measurement data point.
   */
  protected function extractValues(): array {
    $values = [];
    foreach ($this->getTotals() as $item) {
      $values[$item->getMetric()->getMachineName()] = $item->getValue();
    }
    return $values;
  }

  /**
   * Get the totals from the attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Facts\AttachmentFact[]
   *   An array of attachment fact objects.
   */
  public function getTotals(): array {
    $data = $this->getRawData();
    // Extract the values.
    return array_map(fn ($item) => new MeasurementFact($item), $data->totals ?? []);
  }

  /**
   * Get the prototype for an attachment.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype|null
   *   The attachment prototype object.
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
    $query_handler = $this->getAttachmentPrototypeQuery();
    if (!$query_handler) {
      return NULL;
    }
    $plan_id = $measurement->PlanId ?? NULL;
    $prototype_id = $measurement->AttachmentPrototypeId ?? ($measurement->attachmentPrototypeId ?? NULL);
    if ($plan_id && $prototype_id && $prototype = $query_handler->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    $prototype = $prototype_id ? $query_handler->getPrototype($prototype_id) : NULL;
    if (!$prototype instanceof AttachmentPrototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $measurement->Id));
    }
    $this->prototype = $prototype;
    return $this->prototype;
  }

}
