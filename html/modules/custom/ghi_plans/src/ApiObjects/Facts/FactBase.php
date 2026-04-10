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
    $this->deliveryModalityId = $data->DeliveryModalityId;
    $this->customMetricName = $data->CustomMetricName;
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
    $category_properties = [
      'ageGroupId' => 'ageGroups',
      'deliveryModalityId' => 'deliveryModalities',
      'disabilityStatusId' => 'disabilityStatuses',
      'genderId' => 'genders',
      'healthInterventionCategoryId' => 'healthInterventionCategories',
      'populationStatusId' => 'populationStatuses',
      'settlementTypeId' => 'settlementTypes',
    ];
    $category_query = $this->getCategoryQuery();
    $categories = [];
    foreach ($category_properties as $property_name => $namespace) {
      if (!property_exists($this, $property_name) || empty($this->$property_name)) {
        continue;
      }
      $category = $category_query?->getCategory($namespace, $this->$property_name);
      if (!$category) {
        continue;
      }
      $categories[$category->id()] = $category;
    }
    return $categories;
  }

}
