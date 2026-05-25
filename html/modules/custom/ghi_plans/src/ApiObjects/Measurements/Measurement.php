<?php

namespace Drupal\ghi_plans\ApiObjects\Measurements;

use Drupal\Core\Render\Markup;
use Drupal\ghi_plans\ApiObjects\Facts\MeasurementFact;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Helpers\PlanEntityHelper;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\Unit;

/**
 * Abstraction class for API measurement objects.
 */
class Measurement extends ApiObjectBase implements MeasurementInterface {

  use PlanQueryTrait;
  use DisaggregatedDataTrait;

  /**
   * The type.
   *
   * @var string
   */
  protected string $type;

  /**
   * The source data.
   *
   * @var object
   */
  protected object $source;

  /**
   * The attachment id.
   *
   * @var int
   */
  protected int $attachmentId;

  /**
   * The attachment prototype id.
   *
   * @var int
   */
  protected int $attachmentPrototypeId;

  /**
   * The plan id.
   *
   * @var int
   */
  protected int $planId;

  /**
   * The custom id.
   *
   * @var string|null
   */
  protected ?string $customId;

  /**
   * The composed reference.
   *
   * @var string|null
   */
  protected ?string $composedReference;

  /**
   * The description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * The comment.
   *
   * @var string|null
   */
  protected ?string $comment;

  /**
   * The values.
   *
   * @var array
   */
  protected array $values;

  /**
   * The unit.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\Unit|null
   */
  protected ?Unit $unit;

  /**
   * The monitoring period.
   *
   * @var int
   */
  protected int $monitoringPeriod;

  /**
   * Whether the measurement has disaggregated data.
   *
   * @var bool
   */
  protected bool $hasDisaggregatedData;

  /**
   * The calculation method.
   *
   * @var string|null
   */
  protected ?string $calculationMethod;

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
  public function __construct(object $data) {
    parent::__construct($data);
    $query = $this->getEntityTypeQuery();
    $this->processTotals((array) ($data->measurementFact?->items ?? []));

    $this->type = strtolower($data->MeasurementType);
    $this->source = (object) [
      'entity_type' => PlanEntityHelper::checkObjectType($data->EntityMainType ?? NULL),
      'entity_id' => $data->EntityId ?? NULL,
      'plan_id' => $data->PlanId ?? NULL,
    ];
    $this->attachmentId = $data->AttachmentId;
    $this->attachmentPrototypeId = $data->AttachmentPrototypeId;
    $this->planId = $data->PlanId;
    $this->customId = $data->CustomReference ?? NULL;
    $this->composedReference = $data->ComposedReference ?? NULL;
    $this->description = $data->Name ?? NULL;
    $this->comment = !empty($data->IsCommentPublic) ? ($data->Comments ?? NULL) : NULL;
    $this->values = $this->extractValues($this->totals);
    $this->unit = ($data->UnitId ?? NULL) ? $query->getUnit($data->UnitId) : NULL;
    $this->monitoringPeriod = $data->MeasurementPeriodId ?? NULL;
    $this->hasDisaggregatedData = TRUE;
    $this->calculationMethod = ($data->CalculationMethod ?? NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->source?->plan_id;
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
    return $this->attachmentId;
  }

  /**
   * {@inheritdoc}
   */
  public function getReportingPeriodId() {
    return $this->monitoringPeriod;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataPointValue($metric_type) {
    // This includes calculated fields.
    return $this->values[$metric_type] ?? NULL;
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
    return $this->values;
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

    // First see if we can extract the prototype from the plan. This is better
    // for performance when we need to do this for multiple attachments
    // belonging to the same plan (which is the usual case) because the
    // requests are cached.
    $attachment_prototype_query = $this->getAttachmentPrototypeQuery();
    if (!$attachment_prototype_query) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $this->id()));
    }
    $plan_id = $this->planId ?? NULL;
    $prototype_id = $this->attachmentPrototypeId ?? NULL;
    if ($plan_id && $prototype_id && $prototype = $attachment_prototype_query->getPrototypeByPlanAndId($plan_id, $prototype_id)) {
      return $prototype;
    }

    // If that didn't work, we query the prototype data directly.
    $prototype = $prototype_id ? $attachment_prototype_query->getPrototype($prototype_id) : NULL;
    if (!$prototype instanceof AttachmentPrototype) {
      throw new \Exception(sprintf('Failed to extract prototype for attachment %s', $this->id()));
    }
    $this->prototype = $prototype;
    return $this->prototype;
  }

}
