<?php

namespace Drupal\ghi_plans\ApiObjects\Facts;

use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_api\Traits\DateTimeTrait;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction for fact objects.
 */
abstract class FactBase extends ApiObjectBase {

  use PlanReportingPeriodTrait;
  use SimpleCacheTrait;
  use DateTimeTrait;
  use PlanQueryTrait;

  /**
   * Fact properties that identify disaggregation categories.
   *
   * This is the single source for translating between raw Fabric fact fields,
   * fact object properties, and category query namespaces. Map queries use the
   * field names for lightweight filters, while modal rendering uses the
   * namespaces to resolve readable category labels.
   */
  private const DISAGGREGATION_CATEGORY_PROPERTIES = [
    'genderId' => [
      'field' => 'GenderId',
      'namespace' => 'genders',
    ],
    'ageGroupId' => [
      'field' => 'AgeGroupId',
      'namespace' => 'ageGroups',
    ],
    'populationStatusId' => [
      'field' => 'PopulationStatusId',
      'namespace' => 'populationStatuses',
    ],
    'settlementTypeId' => [
      'field' => 'SettlementTypeId',
      'namespace' => 'settlementTypes',
    ],
    'disabilityStatusId' => [
      'field' => 'DisabilityStatusId',
      'namespace' => 'disabilityStatuses',
    ],
    'healthInterventionCategoryId' => [
      'field' => 'HealthInterventionCategoryId',
      'namespace' => 'healthInterventionCategories',
    ],
    'maternalStatusId' => [
      'field' => 'MaternalStatusId',
      'namespace' => 'maternalStatuses',
    ],
    'disaggregationCategoryOtherId' => [
      'field' => 'DisaggregationCategoryOtherId',
      'namespace' => 'disaggregationCategoryOthers',
    ],
    'deliveryModalityId' => [
      'field' => 'DeliveryModalityId',
      'namespace' => 'deliveryModalities',
    ],
  ];

  /**
   * The attachment id.
   *
   * @var int
   */
  protected int $attachmentId;

  /**
   * The metric id.
   *
   * @var int
   */
  protected int $metricId;

  /**
   * The location id.
   *
   * @var int|null
   */
  protected ?int $locationId;

  /**
   * The gender id.
   *
   * @var int|null
   */
  protected ?int $genderId;

  /**
   * The age group id.
   *
   * @var int|null
   */
  protected ?int $ageGroupId;

  /**
   * The population status id.
   *
   * @var int|null
   */
  protected ?int $populationStatusId;

  /**
   * The settlement type id.
   *
   * @var int|null
   */
  protected ?int $settlementTypeId;

  /**
   * The disability status id.
   *
   * @var int|null
   */
  protected ?int $disabilityStatusId;

  /**
   * The health intervention category id.
   *
   * @var int|null
   */
  protected ?int $healthInterventionCategoryId;

  /**
   * The maternal status id.
   *
   * @var int|null
   */
  protected ?int $maternalStatusId;

  /**
   * The disaggregation category other id.
   *
   * @var int|null
   */
  protected ?int $disaggregationCategoryOtherId;

  /**
   * The delivery modality id.
   *
   * @var int|null
   */
  protected ?int $deliveryModalityId;

  /**
   * The custom metric name.
   *
   * @var string|null
   */
  protected ?string $customMetricName;

  /**
   * The source of a calculated field.
   *
   * @var string|null
   */
  protected ?string $calculatedFieldSource = NULL;

  /**
   * Whether this fact represents a total.
   *
   * @var bool
   */
  protected bool $isTotal;

  /**
   * The value.
   *
   * @var float
   */
  protected float $value;

  /**
   * The metric type used by the fact.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\MetricType
   */
  private $metric = NULL;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $this->attachmentId = $data->AttachmentId;
    $this->metricId = $data->MetricTypeId;
    $this->locationId = $data->LocationId ?? NULL;
    $this->genderId = $data->GenderId;
    $this->ageGroupId = $data->AgeGroupId;
    $this->populationStatusId = $data->PopulationStatusId;
    $this->settlementTypeId = $data->SettlementTypeId;
    $this->disabilityStatusId = $data->DisabilityStatusId;
    $this->healthInterventionCategoryId = $data->HealthInterventionCategoryId;
    $this->maternalStatusId = $data->MaternalStatusId;
    $this->disaggregationCategoryOtherId = $data->DisaggregationCategoryOtherId;
    $this->deliveryModalityId = $data->DeliveryModalityId;
    $this->customMetricName = $data->CustomMetricName;
    $this->calculatedFieldSource = $data->DerivedMetricSource ?? NULL;
    $this->isTotal = $data->IsTotal;
    $this->value = $data->ValueNum;
  }

  /**
   * Get the metric type for this fact.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\MetricType|null
   *   The metric type or NULL.
   */
  public function getMetric(): ?MetricType {
    if ($this->metric) {
      return $this->metric;
    }
    $this->metric = $this->getEntityTypeQuery()?->getMetricType($this->metricId) ?? NULL;
    return $this->metric;
  }

  /**
   * Whether this fact represents a total value.
   *
   * @return bool
   *   TRUE if the fact is a total, FALSE otherwise.
   */
  public function isTotal() {
    return $this->isTotal;
  }

  /**
   * Get the value.
   *
   * @return float
   *   The value of the fact.
   */
  public function getValue(): mixed {
    return $this->value;
  }

  /**
   * Get the location id.
   *
   * @return int
   *   The location id of the fact.
   */
  public function getLocationId(): ?int {
    return $this->locationId ?? NULL;
  }

  /**
   * Get the raw Fabric field names that identify disaggregation categories.
   *
   * @return string[]
   *   The disaggregation category field names.
   */
  public static function getDisaggregationCategoryFieldNames(): array {
    return array_column(self::DISAGGREGATION_CATEGORY_PROPERTIES, 'field');
  }

  /**
   * Check whether a raw fact row has disaggregation categories.
   *
   * @param object $fact
   *   The raw Fabric fact row.
   *
   * @return bool
   *   TRUE if the fact row has any category field set.
   */
  public static function rawFactHasDisaggregationCategories(object $fact): bool {
    foreach (self::getDisaggregationCategoryFieldNames() as $field_name) {
      // Raw Fabric rows use NULL category ids for location totals.
      if (!empty($fact->{$field_name})) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check whether this fact has disaggregation categories.
   *
   * @return bool
   *   TRUE if this fact has any category property set.
   */
  public function hasDisaggregationCategories(): bool {
    foreach (array_keys(self::DISAGGREGATION_CATEGORY_PROPERTIES) as $property_name) {
      // Instantiated facts expose the same ids through normalized properties.
      if (property_exists($this, $property_name) && !empty($this->{$property_name})) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get an identifier for the used categories.
   *
   * @return string|null
   *   A category identifier string.
   */
  public function getCombinedCategoryIdentifier(): ?string {
    $categories = $this->getCategories();
    if (empty($categories)) {
      return NULL;
    }
    return implode('|', array_map(fn ($category) => $category->getUuid(), $categories));
  }

  /**
   * Get a combined name for the used categories.
   *
   * @return string|null
   *   A category label string.
   */
  public function getCombinedCategoryLabel(): ?string {
    $categories = $this->getCategories();
    if (empty($categories)) {
      return NULL;
    }
    return implode(' ', array_map(fn ($category) => $category->getDescription() ?: $category->getName(), $categories));
  }

  /**
   * Get the categories that a fact applies to.
   *
   * @return \Drupal\hpc_api\ApiObjects\CategoryInterface[]
   *   An array of category.
   */
  public function getCategories() {
    $category_query = $this->getCategoryQuery();
    $categories = [];
    foreach (self::DISAGGREGATION_CATEGORY_PROPERTIES as $property_name => $category_info) {
      if (!property_exists($this, $property_name) || empty($this->$property_name)) {
        continue;
      }
      // Category ids are scoped by namespace in Fabric, so the raw id alone is
      // not enough to resolve a label.
      $category = $category_query?->getCategory($category_info['namespace'], $this->$property_name);
      if (!$category) {
        continue;
      }
      $categories[$category->id()] = $category;
    }
    return $categories;
  }

}
