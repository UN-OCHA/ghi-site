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
   * The funding.
   *
   * @var string
   */
  protected string $funding;

  /**
   * The coverage.
   *
   * @var float
   */
  protected float $coverage;

  /**
   * The required footnote.
   *
   * @var string|null
   */
  protected ?string $requiredFootnote;

  /**
   * The plan status.
   *
   * @var bool
   */
  protected bool $planStatus;

  /**
   * The target node id.
   *
   * @var string
   */
  protected string $targetNodeId;

  /**
   * The caseload values.
   *
   * @var array
   */
  protected array $caseloadValues;

  /**
   * Constructs a PlanOverviewPlanMock object.
   *
   * @param object $data
   *   The raw data object.
   */
  public function __construct(object $data) {
    $link = (array) ($data->link ?? []);
    $this->rawData = $data;
    $this->id = md5($data->plan_name);
    $this->name = $data->plan_name;
    $this->planStatus = $data->plan_status ?? FALSE;
    $this->funding = (int) ($data->total_funding ?? 0);
    $this->requirements = (int) ($data->total_requirements ?? 0);
    $this->requiredFootnote = $data->required_footnote ?: NULL;
    $this->coverage = (float) ($data->funding_progress ?? 0) * 100;
    // We support to pass in a value structure from an entity reference (or
    // entity_autocomplete for that matter). We assume it's a node reference.
    $this->targetNodeId = NestedArray::getValue($link, [0, 'target_id']);
    $this->isPartOfGHO = $data->in_gho ?? FALSE;
    $this->caseloadValues = [
      'people_in_need' => $data->people_in_need ?? NULL,
      'people_target' => $data->people_target ?? NULL,
      'people_reached_percent' => $data->people_reached_percent ?? NULL,
      'estimated_reached' => $data->estimated_reached ?? NULL,
    ];
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
    if (!$this->targetNodeId) {
      return NULL;
    }
    return Link::fromTextAndUrl($this->name, Url::fromRoute('entity.node.canonical', [
      'node' => $this->targetNodeId,
    ]));
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanStatus() {
    return $this->planStatus;
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
    return TaxonomyHelper::getTermById($this->planType, 'plan_type');
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
  public function getCaseloadValue(string $metric_type, ?string $metric_name = NULL): ?float {
    $map = [
      'in_need' => 'people_in_need',
      'target' => 'people_target',
      'reached_percent' => 'people_reached_percent',
      'expected_reach' => 'estimated_reached',
    ];
    if (!array_key_exists($metric_type, $map)) {
      return NULL;
    }
    return (int) $this->caseloadValues[$map[$metric_type]] ?? NULL;
  }

  /**
   * Get the requirements footnote.
   */
  public function getRequirementsFootnote(): ?string {
    return $this->requiredFootnote;
  }

}
