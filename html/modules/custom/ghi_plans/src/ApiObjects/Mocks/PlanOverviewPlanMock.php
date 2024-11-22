<?php

namespace Drupal\ghi_plans\ApiObjects\Mocks;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\hpc_common\Helpers\FieldHelper;
use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * Abstraction class for a mocked plan partial object.
 *
 * This kind of partial object is a stripped-down, limited-data, object that
 * appears in some specific endpoints. We map this here to provide type hinting
 * and abstracted data access.
 * This specific class represents a mocked object of the same type that can be
 * used to merge custom write-in rows to tables that display objects of this
 * type.
 */
class PlanOverviewPlanMock extends PlanOverviewPlan {

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    $link = (array) ($data->link ?? []);
    return (object) [
      'id' => md5($data->plan_name),
      'name' => $data->plan_name,
      'funding' => (int) ($data->total_funding ?? 0),
      'requirements' => (int) ($data->total_requirements ?? 0),
      'coverage' => (float) ($data->funding_progress ?? 0) * 100,
      // We support to pass in a value structure from an entity reference (or
      // entity_autocomplete for that matter). We assume it's a node reference.
      'target_node_id' => NestedArray::getValue($link, [0, 'target_id']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return 'plan';
  }

  /**
   * Get the base object entity corresponding to this API object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan entity.
   */
  public function getEntity() {
    return NULL;
  }

  /**
   * Get a link associated to this mock object.
   *
   * We support to pass in a value structure from an entity reference (or
   * entity_autocomplete for that matter). We assume it's a node reference.
   *
   * @return \Drupal\Core\Link|null
   *   A link object or NULL.
   */
  public function toLink() {
    if (!$this->target_node_id) {
      return NULL;
    }
    return Link::fromTextAndUrl($this->name, Url::fromRoute('entity.node.canonical', [
      'node' => $this->target_node_id,
    ]));
  }

  /**
   * Get the plan status as stored.
   *
   * @return bool
   *   The plan status if available.
   */
  public function getPlanStatus() {
    $raw_data = $this->getRawData();
    return $raw_data->plan_status ?? FALSE;
  }

  /**
   * Get the plan status label.
   *
   * @return string
   *   The human readable plan status if available.
   */
  public function getPlanStatusLabel() {
    $plan_status_options = FieldHelper::getBooleanFieldOptions('base_object', 'plan', 'field_released');
    return $plan_status_options[$this->getPlanStatus()] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanType() {
    return TaxonomyHelper::getTermById($this->getRawData()->plan_type, 'plan_type');
  }

  /**
   * Get the type of a plan.
   *
   * @return string
   *   The plan type name.
   */
  public function getOriginalTypeName($fetch_from_entity = FALSE) {
    return $this->getPlanType()->label();
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
   * Get a caseload value.
   *
   * @param string $metric_type
   *   The metric type.
   * @param string $metric_name
   *   The english metric name.
   * @param string $fallback_type
   *   The metric type of a fallback.
   *
   * @return int|float
   *   The caseload value if found.
   */
  public function getCaseloadValue($metric_type, $metric_name = NULL, $fallback_type = NULL) {
    $raw_data = $this->getRawData();
    $map = [
      'inNeed' => 'people_in_need',
      'target' => 'people_target',
      'reached_percent' => 'people_reached_percent',
      'expectedReach' => 'estimated_reached',
    ];
    if (!array_key_exists($metric_type, $map)) {
      return NULL;
    }
    return $raw_data->{$map[$metric_type]} ?? NULL;
  }

  /**
   * Get the requirements footnote.
   */
  public function getRequirementsFootnote() {
    $raw_data = $this->getRawData();
    return !empty($raw_data->required_footnote) ? $raw_data->required_footnote : NULL;
  }

}
