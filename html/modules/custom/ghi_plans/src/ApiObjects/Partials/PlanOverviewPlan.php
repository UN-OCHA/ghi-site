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
   * @var float
   */
  protected float $requirements;

  /**
   * The funding.
   *
   * @var float
   */
  protected float $funding;

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
   * @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment[]
   */
  private $caseloads;

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
    $this->requirements = ($data->requirements ?? NULL) ?: 0;
    $this->funding = ($data->funding ?? NULL) ?: 0;
    $this->isPartOfGHO = $plan->isPartOfGho();
    $this->lastPublishedReportingPeriodId = $plan->getLastPublishedReportingPeriodId();
    $this->countries = $plan->getCountries();
    $this->caseloads = $data->caseloads ?? [];
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
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan entity.
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
   * @return string
   *   The plan type name.
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
   * @return string
   *   The plan type name.
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
   * Check if the plan is part of the GHO.
   *
   * @return bool
   *   TRUE if the plan is partof the GHO, FALSE otherwise.
   */
  public function isPartOfGho() {
    return $this->isPartOfGHO;
  }

  /**
   * Get the coverage for a plan based on the given funding.
   *
   * @return float
   *   The coverage for a plan.
   */
  public function getCoverage(): float {
    return (float) CommonHelper::calculateRatio($this->getFunding() ?: 0, $this->getRequirements()) * 100;
  }

  /**
   * Get the requirements for a plan.
   *
   * @return float
   *   The plan requirements.
   */
  public function getRequirements(): float {
    return (float) $this->requirements;
  }

  /**
   * Get the funding for a plan.
   *
   * @return float
   *   The plan funding.
   */
  public function getFunding(): float {
    return (float) $this->funding;
  }

  /**
   * Check if the current plan partial has caseloads.
   *
   * @return bool
   *   TRUE of the plan has caseloads, FALSE otherwise.
   */
  private function hasCaseloads(): bool {
    return !empty($this->caseloads);
  }

  /**
   * Get a caseload value.
   *
   * @param string $metric_type
   *   The metric type.
   * @param string $metric_name
   *   The english metric name.
   *
   * @return int
   *   The caseload value if found.
   */
  public function getCaseloadValue(string $metric_type, ?string $metric_name = NULL): ?float {
    if (!$this->hasCaseloads()) {
      return NULL;
    }

    foreach ($this->caseloads as $caseload) {
      $value = $caseload->getCaseloadValue($metric_type, $metric_name);
      if ($value !== NULL) {
        return $value;
      }
    }
    return NULL;
  }

  /**
   * Get the fields of the plan caseload attachment.
   *
   * @param int $attachment_id
   *   Optional argument to retrieve a specific caseload.
   *
   * @return array
   *   An array of caseload fields.
   */
  public function getPlanCaseloadFields($attachment_id = NULL): array {
    $caseload = $this->getPlanCaseload($attachment_id);
    return $caseload?->getFields() ?? [];
  }

  /**
   * Get the plan caseload attachment.
   *
   * @param int $attachment_id
   *   Optional argument to retrieve a specific caseload.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface|null
   *   A caseload object or NULL.
   */
  public function getPlanCaseload(?int $attachment_id = NULL): ?CaseloadAttachmentInterface {
    $attachment_id = ($attachment_id ?? $this->getEntity()?->getPlanCaseloadId()) ?? array_key_first($this->caseloads);
    return $attachment_id ? $this->findPlanCaseload($this->caseloads, $attachment_id) : NULL;
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
