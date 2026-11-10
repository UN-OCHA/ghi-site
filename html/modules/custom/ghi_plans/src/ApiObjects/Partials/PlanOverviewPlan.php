<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\ghi_plans\Traits\PlanTypeTrait;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_api\Helpers\StringHelper;

/**
 * Abstraction class for a plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanOverviewPlan extends BaseObject {

  use PlanReportingPeriodTrait;
  use PlanTypeTrait;
  use AttachmentFilterTrait;

  /**
   * The plan type.
   *
   * @var string|null
   */
  protected ?string $planType;

  /**
   * The requirements.
   *
   * @var float|null
   */
  protected ?float $requirements;

  /**
   * The funding.
   *
   * @var float|null
   */
  protected ?float $funding;

  /**
   * Whether the plan is part of the GHO.
   *
   * @var bool
   */
  protected bool $isPartOfGHO;

  /**
   * The last published reporting period id.
   *
   * @var int|null
   */
  protected ?int $lastPublishedReportingPeriodId;

  /**
   * The countries.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Country[]
   */
  protected array $countries;

  /**
   * The caseloads.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface[]
   */
  private $caseloads;

  /**
   * Normalized source-aware plan metrics.
   */
  private PlanMetricValues $metrics;

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    /** @var \Drupal\ghi_plans\ApiObjects\Plan $plan */
    $plan = $data->plan;
    $this->rawData = $data;
    $this->id = $plan->id();
    $this->name = $plan->getName();
    $this->planType = $plan->getPlanType()?->getName() ?? NULL;
    // Keep direct construction compatible with callers that still supply the
    // former caseload and financial properties instead of normalized metrics.
    $this->metrics = $data->metrics ?? new PlanMetricValues(Plan::DATA_SOURCE_PLAN, array_filter([
      'requirements' => $data->requirements ?? NULL,
      'funding' => $data->funding ?? NULL,
    ], fn ($value) => $value !== NULL), $data->caseloads ?? []);
    $this->requirements = $this->metrics->get('requirements');
    $this->funding = $this->metrics->get('funding');
    $this->isPartOfGHO = $plan->isPartOfGho();
    $this->lastPublishedReportingPeriodId = $plan->getLastPublishedReportingPeriodId();
    $this->countries = $plan->getCountries();
    $this->caseloads = $this->metrics->getCaseloads();
    usort($this->caseloads, fn($a, $b) => strcmp($a->getCustomId(), $b->getCustomId()));
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return 'plan';
  }

  /**
   * Get the plan status if an entity is available.
   *
   * @return bool|null
   *   The plan status as a boolean, or NULL.
   */
  public function getPlanStatus() {
    return $this->getEntity()?->isReleased() ?? FALSE;
  }

  /**
   * Get the plan status label if an entity is available.
   *
   * @return string|null
   *   The plan status as a human readable string, or NULL.
   */
  public function getPlanStatusLabel() {
    return $this->getEntity()?->getPlanStatusLabel() ?? NULL;
  }

  /**
   * Get the plan document uri if an entity is available.
   *
   * @return string|null
   *   The plan document uri, or NULL.
   */
  public function getPlanDocumentUri() {
    return $this->getEntity()?->getDocumentUri() ?? NULL;
  }

  /**
   * Get the base object entity corresponding to this API object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan|null
   *   The plan entity, or NULL when no imported plan matches.
   */
  public function getEntity() {
    $entity = parent::getEntity();
    return $entity instanceof Plan ? $entity : NULL;
  }

  /**
   * Get the plan type.
   *
   * @return \Drupal\ghi_plans\Entity\PlanType|null
   *   The plan type.
   */
  public function getPlanType() {
    return $this->getEntity()?->getPlanType();
  }

  /**
   * Get the type of a plan.
   *
   * @return string|null
   *   The plan type name, or NULL when unavailable.
   */
  public function getTypeName($fetch_from_entity = FALSE) {
    if ($fetch_from_entity && $plan_type = $this->getPlanType()) {
      return $plan_type->label();
    }
    return $this->planType ?? NULL;
  }

  /**
   * Get the shortname type of a plan.
   *
   * @return string|null
   *   The abbreviated plan type name, or NULL when unavailable.
   */
  public function getTypeShortName() {
    if ($plan_type = $this->getPlanType()) {
      return $plan_type->getAbbreviation();
    }
    $type_name = $this->getTypeName(TRUE);
    return $type_name ? StringHelper::getAbbreviation($type_name) : NULL;
  }

  /**
   * Get the order number for the type.
   *
   * This is the numerical order based on the current plan type term order,
   * that should be used to sort plans belonging to the same focus location.
   *
   * @return int|null
   *   The order number according to the manually selected sort order of the
   *   plan type term objects.
   */
  public function getTypeOrder() {
    $plan_type = $this->getPlanType();
    if (!$plan_type) {
      return NULL;
    }
    $type_order = $this->getAvailablePlanTypes();
    return array_flip($type_order)[$plan_type->label()] ?? NULL;
  }

  /**
   * Check if the plan is of the given type.
   *
   * @param string $type_name
   *   The type name to check.
   *
   * @return bool
   *   TRUE if the plan is of the given type, FALSE otherwise.
   */
  public function isType($type_name) {
    $name = $this->getTypeName();
    if (empty($name)) {
      return FALSE;
    }
    return $name == $type_name;
  }

  /**
   * Check if the plan is an HRP.
   *
   * @return bool
   *   TRUE if the plan is an HRP, FALSE otherwise.
   */
  public function isHrp() {
    return $this->isType('Humanitarian response plan');
  }

  /**
   * Check if the plan is an RRP.
   *
   * @return bool
   *   TRUE if the plan is an RRP, FALSE otherwise.
   */
  public function isRrp() {
    return $this->isType('Regional response plan');
  }

  /**
   * Check if the plan is a Flash Appeal.
   *
   * @return bool
   *   TRUE if the plan is a Flash Appeal, FALSE otherwise.
   */
  public function isFlashAppeal() {
    return $this->isType('Flash appeal');
  }

  /**
   * Check if the plan is of type Other.
   *
   * @return bool
   *   TRUE if the plan is of type Other, FALSE otherwise.
   */
  public function isOther() {
    return empty($this->getTypeName()) || $this->isType('Other');
  }

  /**
   * Checks whether the plan is part of the GHO.
   *
   * @return bool
   *   TRUE if the plan is part of the GHO, FALSE otherwise.
   */
  public function isPartOfGho() {
    return $this->isPartOfGHO;
  }

  /**
   * Gets coverage from the selected source's funding and requirements.
   *
   * @param int $precision
   *   The precision to use (number of digits after the decimal point).
   *
   * @return float|null
   *   The coverage, or NULL when either source value is unavailable.
   */
  public function getCoverage(int $precision = 1): ?float {
    $funding = $this->getFunding();
    $requirements = $this->getRequirements();
    return $funding !== NULL && $requirements !== NULL ? (float) CommonHelper::calculateRatio($funding, $requirements, $precision + 2) * 100 : NULL;
  }

  /**
   * Get the requirements for a plan.
   *
   * @return int|null
   *   The plan requirements, or NULL when the selected source has no value.
   */
  public function getRequirements(): ?int {
    return $this->requirements !== NULL ? (int) $this->requirements : NULL;
  }

  /**
   * Get the funding for a plan.
   *
   * @return int|null
   *   The plan funding, or NULL when the selected source has no value.
   */
  public function getFunding(): ?int {
    return $this->funding !== NULL ? (int) $this->funding : NULL;
  }

  /**
   * Get a caseload value.
   *
   * @param string $metric_type
   *   The metric type.
   * @param string|null $metric_name
   *   The English metric name when an attachment lookup needs it.
   *
   * @return int|float|null
   *   The caseload value if found.
   */
  public function getCaseloadValue(string $metric_type, ?string $metric_name = NULL): int|float|null {
    // GHO-backed plans have no caseload objects, so a missing normalized value
    // remains missing instead of falling back to Country Module data. The
    // attachment fallback only supports legacy Country Module metric types not
    // included in the normalized set.
    if ($this->metrics->has($metric_type)) {
      return (int) $this->metrics->get($metric_type);
    }
    $plan_caseload = $this->getPlanCaseload();
    if (!$plan_caseload) {
      return NULL;
    }

    $value = $plan_caseload->getCaseloadValue($metric_type, $metric_name);
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * Gets the fields available for plan-level population aggregation.
   *
   * GHO-backed plans expose normalized values directly. Country Module plans
   * expose fields from their selected caseload attachment.
   *
   * @param int|null $attachment_id
   *   Optional argument to retrieve a specific caseload.
   *
   * @return array
   *   An array of caseload fields.
   */
  public function getPlanCaseloadFields($attachment_id = NULL): array {
    if ($this->metrics->getSource() === Plan::DATA_SOURCE_GHO) {
      return $this->metrics->getValues();
    }
    $caseload = $this->getPlanCaseload($attachment_id);
    return $caseload?->getFields() ?? [];
  }

  /**
   * Gets the source used for plan-level metrics.
   *
   * @return string
   *   The resolved source.
   */
  public function getMetricSource(): string {
    return $this->metrics->getSource();
  }

  /**
   * Gets the prioritized target when supplied by the selected source.
   *
   * @return int|null
   *   The prioritized target.
   */
  public function getPrioritizedTarget(): ?int {
    $value = $this->metrics->get('prioritized_target');
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * Gets prioritized requirements when supplied by the selected source.
   *
   * @return int|null
   *   The prioritized requirements.
   */
  public function getPrioritizedRequirements(): ?int {
    $value = $this->metrics->get('prioritized_requirements');
    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * Get the plan caseload attachment.
   *
   * @param int|null $attachment_id
   *   Optional argument to retrieve a specific caseload.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface|null
   *   A caseload object or NULL.
   */
  public function getPlanCaseload(?int $attachment_id = NULL): ?CaseloadAttachmentInterface {
    $attachment_id = ($attachment_id ?? $this->getEntity()?->getPlanCaseloadId()) ?? NULL;
    return $this->findPlanCaseload($this->caseloads, $attachment_id);
  }

  /**
   * Get the last published reporting period.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The reporting period object or NULL.
   */
  public function getLastPublishedReportingPeriod(): ?PlanReportingPeriod {
    if (!$this->lastPublishedReportingPeriodId) {
      return NULL;
    }
    return $this->getPlanReportingPeriod($this->id(), $this->lastPublishedReportingPeriodId);
  }

  /**
   * Get the countries associated to a plan partial.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country[]
   *   An array of country objects, keyed by the country id.
   */
  public function getCountries(): array {
    return $this->countries;
  }

  /**
   * Get the country for a plan.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   A country object or NULL.
   */
  public function getCountry(): ?Country {
    $countries = $this->getCountries();
    return count($countries) ? reset($countries) : NULL;
  }

}
