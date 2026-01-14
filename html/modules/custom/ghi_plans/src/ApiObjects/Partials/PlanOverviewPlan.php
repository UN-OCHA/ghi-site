<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\ghi_plans\Traits\PlanTypeTrait;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Helpers\StringHelper;

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
   * The caseloads.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachment[]
   */
  private $caseloads;

  /**
   * {@inheritdoc}
   */
  protected function map() {
    $data = $this->getRawData();

    $this->caseloads = array_map(function ($item) {
      return new CaseloadAttachment($item);
    }, $data->caseloads ?? []);

    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'requirements' => $data->requirements ?: 0,
    ];
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
    return $this->getRawData()->PlanType ?? NULL;
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
    return $this->getRawData()->IsPartOfGHO ?? FALSE;
  }

  /**
   * Get the coverage for a plan based on the given funding.
   *
   * @param float $funding
   *   The funding to calculate the coverage against.
   *
   * @return float
   *   The coverage for a plan.
   */
  public function getCoverage(float $funding): float {
    return (float) CommonHelper::calculateRatio($funding, $this->getRequirements()) * 100;
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
   * @param string $metric_name
   *   The english metric name.
   *
   * @return int
   *   The caseload value if found.
   */
  public function getCaseloadValue($metric_name): ?float {
    if (!$this->hasCaseloads()) {
      return NULL;
    }

    foreach ($this->caseloads as $caseload) {
      $totals = $caseload->getTotals();
      if (empty($totals)) {
        continue;
      }
      foreach ($totals as $total) {
        if (!$total->getMetric()) {
          continue;
        }
        if ($total->getMetric()->getName() == $metric_name) {
          return $total->getValue();
        }
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
  public function getPlanCaseloadFields($attachment_id = NULL) {
    $caseload = $this->getPlanCaseload($attachment_id);
    return $caseload?->getOriginalFields() ?? [];
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
  public function getPlanCaseload($attachment_id = NULL) {
    return $this->findPlanCaseload($this->caseloads, $attachment_id ?? $this->getEntity()?->getPlanCaseloadId());
  }

  /**
   * Get the last published reporting period.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The reporting period object or NULL.
   */
  public function getLastPublishedReportingPeriod() {
    $period_id = $this->getRawData()->lastPublishedReportingPeriodId;
    if (!$period_id) {
      return NULL;
    }
    return $this->getPlanReportingPeriod($this->id(), $period_id);
  }

  /**
   * Get the countries associated to a plan partial.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country[]
   *   An array of country objects, keyed by the country id.
   */
  public function getCountries() {
    $countries = [];
    if (empty($this->getRawData()->planLocation?->items)) {
      return $countries;
    }
    foreach ($this->getRawData()->planLocation?->items as $item) {
      $country = new Country($item->location);
      $countries[$country->id()] = $country;
    }
    return $countries;
  }

  /**
   * Get the country for a plan.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country
   *   A country object.
   */
  public function getCountry() {
    $countries = $this->getCountries();
    return count($countries) ? reset($countries) : NULL;
  }

}
