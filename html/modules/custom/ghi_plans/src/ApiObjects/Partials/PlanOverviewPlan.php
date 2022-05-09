<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction class for a plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 */
class PlanOverviewPlan extends BaseObject {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    return (object) [
      'id' => $data->id,
      'name' => $data->name,
      'funding' => $data->funding->totalFunding ?? 0,
      'requirements' => $data->requirements ? $data->requirements->revisedRequirements : 0,
      'coverage' => $data->funding->progress ?? 0,
    ];
  }

  /**
   * Get the plan type object from a plan.
   *
   * @return object
   *   The plan type object if found.
   */
  private function getTypeObject() {
    if (!is_object($this->getRawData()->planType)) {
      return NULL;
    }
    return $this->getRawData()->planType;
  }

  /**
   * Get a property from the plan type object.
   *
   * @param string $property
   *   The property to retrieve.
   *
   * @return mixed|null
   *   The value of the plan type property or NULL if not found.
   */
  private function getTypeProperty($property) {
    $plan_type = $this->getTypeObject();
    if (!$plan_type || !property_exists($plan_type, $property)) {
      return NULL;
    }
    return $plan_type->$property;
  }

  /**
   * Get the type of a plan.
   *
   * @return string
   *   The plan type name.
   */
  public function getTypeName() {
    return $this->getTypeProperty('name');
  }

  /**
   * Get the type of a plan.
   *
   * @return string
   *   The plan type name.
   */
  public function getTypeShortName() {
    return StringHelper::getAbbreviation($this->getTypeName());
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
    if (empty($this->getTypeName())) {
      return FALSE;
    }
    return $this->getTypeName() == $type_name;
  }

  /**
   * Whether the plan is of a type with with the includeTotals property set.
   *
   * @return bool|null
   *   The value of the includeTotals property or NULL if not set.
   */
  public function isTypeIncluded() {
    return (boolean) $this->getTypeProperty('includeTotals');
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
   * Get the funding for a plan.
   *
   * @return int
   *   The plan funding.
   */
  public function getFunding() {
    return $this->funding;
  }

  /**
   * Get the coverage for a plan.
   *
   * @return float
   *   The coverage for a plan.
   */
  public function getCoverage() {
    return $this->coverage;
  }

  /**
   * Get the requirements for a plan.
   *
   * @return int
   *   The plan funding.
   */
  public function getRequirements() {
    return $this->requirements;
  }

  /**
   * Check if the current plan partial has caseloads.
   *
   * @return bool
   *   TRUE of the plan has caseloads, FALSE otherwise.
   */
  private function hasCaseloads() {
    return !empty($this->getRawData()->caseLoads);
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
  public function getCaseloadValue($metric_type, $metric_name = NULL) {
    if (!$this->hasCaseloads()) {
      return NULL;
    }
    $caseload_item = $this->getCaseloadItemByType($metric_type);
    if (!$caseload_item) {
      // Fallback, see https://humanitarian.atlassian.net/browse/HPC-7838
      $caseload_item = $this->getCaseloadItemByName($metric_name);
    }
    return $caseload_item && property_exists($caseload_item, 'value') ? (int) $caseload_item->value : NULL;
  }

  /**
   * Get a caseload item by metric type.
   *
   * @param string $type
   *   The metric type.
   *
   * @return object
   *   A caseload item if found.
   */
  private function getCaseloadItemByType($type) {
    $totals = $this->getRawData()->caseLoads[0]->totals;
    $candidates = array_filter($totals, function ($item) use ($type) {
      return (strtolower($item->type) == strtolower($type));
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
  }

  /**
   * Get a caseload item by metric name.
   *
   * @param string $name
   *   The metric name.
   *
   * @return object
   *   A caseload item if found.
   */
  private function getCaseloadItemByName($name) {
    $totals = $this->getRawData()->caseLoads[0]->totals;
    $candidates = array_filter($totals, function ($item) use ($name) {
      return property_exists($item->name, 'en') && ($item->name->en == $name);
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
  }

}
