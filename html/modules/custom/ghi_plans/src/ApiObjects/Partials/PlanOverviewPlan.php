<?php

namespace Drupal\ghi_plans\ApiObjects\Partials;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\ghi_plans\Traits\PlanTypeTrait;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

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
   * {@inheritdoc}
   */
  public function getBundle() {
    return 'plan';
  }

  /**
   * Get the plan status if an entity is available.
   *
   * @return string|null
   *   The plan status as a human readable string, or NULL.
   */
  public function getPlanStatus() {
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
  public function getOriginalTypeName($fetch_from_entity = FALSE) {
    $type_name = $this->getTypeProperty('name');
    if ($fetch_from_entity && $plan = $this->getEntity()) {
      return $type_name = $plan->getPlanType()?->label();
    }
    return $type_name;
  }

  /**
   * Get the type of a plan.
   *
   * @return string
   *   The plan type name.
   */
  public function getTypeName($fetch_from_entity = FALSE) {
    return $this->getPlanTypeName($this->getOriginalTypeName($fetch_from_entity), $this->isTypeIncluded());
  }

  /**
   * Get the type of a plan.
   *
   * @return string
   *   The plan type name.
   */
  public function getTypeShortName($fetch_from_entity = FALSE) {
    return $this->getPlanTypeShortName($this->getOriginalTypeName($fetch_from_entity), $this->isTypeIncluded());
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
    $name = $this->getOriginalTypeName();
    if (empty($name)) {
      return FALSE;
    }
    return $name == $type_name;
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
   * @param string $fallback_type
   *   The metric type of a fallback.
   *
   * @return int
   *   The caseload value if found.
   */
  public function getCaseloadValue($metric_type, $metric_name = NULL, $fallback_type = NULL) {
    if (!$this->hasCaseloads()) {
      return NULL;
    }
    $caseload_item = $this->getCaseloadItemByType($metric_type);
    if (!$caseload_item && $metric_name !== NULL) {
      // Fallback, see https://humanitarian.atlassian.net/browse/HPC-7838
      $caseload_item = $this->getCaseloadItemByName($metric_name);
    }
    if ($caseload_item && property_exists($caseload_item, 'value')) {
      $value = $caseload_item->value;
      return $value !== NULL ? (int) $caseload_item->value : NULL;
    }
    if ($fallback_type !== NULL) {
      return $this->getCaseloadValue($fallback_type);
    }
    return NULL;
  }

  /**
   * Get a caseload item by metric type.
   *
   * @param string $type
   *   The metric type.
   *
   * @return object|null
   *   A caseload item if found.
   */
  private function getCaseloadItemByType($type) {
    $caseload_items = $this->getPlanCaseloadFields();
    if (!$caseload_items) {
      return NULL;
    }

    $candidates = array_filter($caseload_items, function ($item) use ($type) {
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
   * @return object|null
   *   A caseload item if found.
   */
  private function getCaseloadItemByName($name) {
    $caseload_items = $this->getPlanCaseloadFields();
    if (!$caseload_items) {
      return NULL;
    }

    // We support alternative names based on RPM.
    $alternative_names = [
      // Reached.
      'Reached' => [
        'Atteints',
        'Personas Atendidas',
      ],
      // Cumulative reach.
      'Cumulative reach' => [
        'Cumul atteint',
        'Alcance cumulativo',
      ],
      // Covered.
      'Covered' => [
        'Couverts',
        'Personas con Necesidades Cubiertas',
      ],
    ];

    $candidates = array_filter($caseload_items, function ($item) use ($name, $alternative_names) {
      if (!property_exists($item->name, 'en')) {
        return FALSE;
      }
      $item_name = $item->name->en;
      if ($item_name == $name) {
        return TRUE;
      }
      if (array_key_exists($name, $alternative_names) && in_array($item_name, $alternative_names[$name])) {
        return TRUE;
      }
      return FALSE;
    });
    if (count($candidates) != 1) {
      return NULL;
    }
    return reset($candidates);
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
    if (!$caseload) {
      return [];
    }
    $calculated_fields = $caseload->calculatedFields ?? [];
    if ($calculated_fields && !is_array($calculated_fields)) {
      $calculated_fields = [
        $calculated_fields->type => $calculated_fields,
      ];
    }
    return array_merge($caseload->totals, $calculated_fields);
  }

  /**
   * Get the plan caseload attachment.
   *
   * @param int $attachment_id
   *   Optional argument to retrieve a specific caseload.
   *
   * @return object|null
   *   A caseload object or NULL.
   */
  public function getPlanCaseload($attachment_id = NULL) {
    $caseload = NULL;

    $caseloads = $this->getRawData()->caseLoads ?? [];
    if (empty($caseloads)) {
      return $caseload;
    }

    $plan_entity = $this->getEntity();
    $selected_caseload_id = $plan_entity ? $plan_entity->getPlanCaseloadId() : NULL;

    // Try to either get the requested caseload, or the one selected in the
    // base object.
    $attachment_id = $attachment_id ?? $selected_caseload_id;
    // We have 2 options here. Either a specific attachment has been
    // requested and we use that if it is part of the available attachments.
    if ($attachment_id !== NULL) {
      $matching_caseloads = array_filter($caseloads, function ($caseload) use ($attachment_id) {
        return $caseload->attachmentId == $attachment_id;
      });
      $caseload = !empty($matching_caseloads) && is_array($matching_caseloads) ? reset($matching_caseloads) : NULL;
    }

    // Or we try to deduce the suitable attachment by selecting the one with
    // the lowest custom reference.
    if ($caseload === NULL) {
      ArrayHelper::sortObjectsByProperty($caseloads, 'customReference', EndpointQuery::SORT_ASC, SORT_STRING);
      $caseload = count($caseloads) ? reset($caseloads) : NULL;
    }
    return $caseload;
  }

  /**
   * Get the last published reporting period.
   *
   * @return object|null
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
   * @return array
   *   An array of country objects, keyed by the country id.
   *   Each item has the properties "id", "name" and "latLng".
   */
  public function getCountries() {
    $countries = [];
    if (empty($this->getRawData()->countries)) {
      return $countries;
    }
    foreach ($this->getRawData()->countries as $country) {
      $countries[$country->id] = (object) [
        'id' => $country->id,
        'name' => $country->name,
        'latLng' => [(string) $country->latitude, (string) $country->longitude],
      ];
    }
    return $countries;
  }

  /**
   * Get the country for a plan.
   *
   * @return object
   *   A country object, see self::getCountries() for details.
   */
  public function getCountry() {
    $countries = $this->getCountries();
    return count($countries) ? reset($countries) : NULL;
  }

}
