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
   * {@inheritdoc}
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
      'in_gho' => $data->in_gho ?? FALSE,
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getPlanStatus() {
    $raw_data = $this->getRawData();
    return $raw_data->plan_status ?? FALSE;
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getTypeName($fetch_from_entity = FALSE) {
    return $this->getPlanType()?->label();
  }

  /**
   * {@inheritdoc}
   */
  public function isType($type_name) {
    $name = $this->getTypeName();
    if (empty($name)) {
      return FALSE;
    }
    return $name == $type_name;
  }

  /**
   * {@inheritdoc}
   */
  public function isPartOfGho() {
    return $this->in_gho ?? FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCaseloadValue(string $metric_type, ?string $metric_name = NULL): ?float {
    $raw_data = $this->getRawData();
    $map = [
      'in_need' => 'people_in_need',
      'target' => 'people_target',
      'reached_percent' => 'people_reached_percent',
      'expected_reach' => 'estimated_reached',
    ];
    if (!array_key_exists($metric_type, $map)) {
      return NULL;
    }
    return (int) $raw_data->{$map[$metric_type]} ?? NULL;
  }

  /**
   * Get the requirements footnote.
   */
  public function getRequirementsFootnote() {
    $raw_data = $this->getRawData();
    return !empty($raw_data->required_footnote) ? $raw_data->required_footnote : NULL;
  }

}
