<?php

namespace Drupal\ghi_plans\ApiObjects\Prototypes;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;

/**
 * Abstraction for API entity prototype objects.
 */
class EntityPrototype extends ApiObjectBase {

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The ref code.
   *
   * @var string
   */
  protected string $refCode;

  /**
   * The type.
   *
   * @var string
   */
  protected string $type;

  /**
   * The plan id.
   *
   * @var string
   */
  protected string $planId;

  /**
   * The order number.
   *
   * @var string
   */
  protected string $orderNumber;

  /**
   * The singular name.
   *
   * @var string
   */
  protected string $nameSingular;

  /**
   * The plural name.
   *
   * @var string
   */
  protected string $namePlural;

  /**
   * The can support.
   *
   * @var array|object
   */
  protected array|object $canSupport;

  /**
   * The children.
   *
   * @var array
   */
  protected array $children;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'RefCode',
    'Type',
    'Value',
    'PlanId',
    'OrderNumber',
    'CreatedAt',
    'UpdatedAt',
    'RecordStatus',
    'Source',
    'SourceId',
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct(object $data) {
    parent::__construct($data);
    $value = is_string($data->Value) ? json_decode($data->Value ?? '') : $data->Value;
    $this->name = $value->name->en->singular;
    $this->refCode = $data->RefCode;
    $this->type = strtoupper($data->Type);
    $this->planId = $data->PlanId;
    $this->orderNumber = $data->OrderNumber;
    $this->nameSingular = $value->name->en->singular;
    $this->namePlural = $value->name->en->plural;
    $this->canSupport = $value->canSupport ?? [];
    $this->children = is_array($value->possibleChildren ?? NULL) ? $value->possibleChildren : [];
  }

  /**
   * Get the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the type for the entity prototype.
   *
   * @return string
   *   The type of prototype as a string.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get the singular name of this prototype.
   *
   * @return string
   *   The singular name.
   */
  public function getNameSingular() {
    return $this->nameSingular;
  }

  /**
   * Get the plural name of this prototype.
   *
   * @return string
   *   The plural name.
   */
  public function getNamePlural() {
    return $this->namePlural;
  }

  /**
   * Get the plan id.
   *
   * @return string
   *   The plan id.
   */
  public function getPlanId() {
    return $this->planId;
  }

  /**
   * Whether this entity prototype represents a plan entity.
   *
   * Plan entity in the API sense of the term.
   *
   * @return bool
   *   TRUE if the prototype represents a plan entity, FALSE otherwise.
   */
  public function isPlanEntity() {
    return $this->getType() == 'PE';
  }

  /**
   * Whether this entity prototype represents a governing entity.
   *
   * @return bool
   *   TRUE if the prototype represents a governing entity, FALSE otherwise.
   */
  public function isGoverningEntity() {
    return $this->getType() == 'GVE';
  }

  /**
   * Get the ref code for the entity prototype.
   *
   * @return string
   *   The ref code.
   */
  public function getRefCode() {
    return $this->refCode;
  }

  /**
   * Get the order number for the entity prototype.
   *
   * @return int|null
   *   The order number.
   */
  public function getOrderNumber(): ?int {
    return $this->orderNumber ?? NULL;
  }

  /**
   * Get the ids of supported prototypes.
   *
   * @todo Define what "support" means.
   *
   * @return int[]
   *   An array of prototype ids that this entity prototype supports.
   */
  public function getSupportedPrototypeIds() {
    if (empty($this->canSupport)) {
      return [];
    }
    // The value of can support is either an array or some kind of logical
    // wrapper object. We need an array, so if it's an object, let's see if
    // it's one we know, like xor, and then get it's sub items as an array.
    $canSupport = is_array($this->canSupport) ? $this->canSupport : (is_object($this->canSupport) && property_exists($this->canSupport, 'xor') ? $this->canSupport->xor : []);
    $canSupport = array_filter($canSupport, function ($item) {
      // Ignore items that are not objects, it's probably an "xor" thing that
      // we don't want to handle at the moment.
      return is_object($item) && property_exists($item, 'id');
    });
    return array_filter(array_map(function ($item) {
      return $item->id ?? NULL;
    }, $canSupport));
  }

  /**
   * Get the children.
   *
   * @return object[]
   *   An array of children objects that this entity prototype supports.
   */
  public function getChildren(): array {
    return $this->children ?: [];
  }

  /**
   * Get the ids of children.
   *
   * @todo Define what "children" means.
   *
   * @return int[]
   *   An array of children ids that this entity prototype supports.
   */
  public function getChildrenPrototypeIds() {
    if (empty($this->children)) {
      return [];
    }
    $children = array_filter($this->children, function ($item) {
      // Ignore items that are not objects, it's probably an "xor" thing that
      // we don't want to handle at the moment.
      return is_object($item) && property_exists($item, 'id');
    });
    return array_filter(array_map(function ($item) {
      return $item->id ?? NULL;
    }, $children));
  }

}
