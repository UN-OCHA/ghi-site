<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\Helpers\ArrayHelper;

/**
 * Base class for API entity objects.
 */
abstract class EntityObjectBase extends ApiObjectBase implements EntityObjectInterface {

  use PlanQueryTrait;

  /**
   * The name of the entity.
   *
   * @var string
   */
  protected string $name;

  /**
   * The group name.
   *
   * @var string
   */
  protected string $groupName;

  /**
   * The description.
   *
   * @var string|null
   */
  protected ?string $description;

  /**
   * The entity name.
   *
   * @var string
   */
  protected string $entityName;

  /**
   * The plan id.
   *
   * @var int|null
   */
  protected ?int $planId;

  /**
   * The prototype id.
   *
   * @var int|null
   */
  protected ?int $prototypeId;

  /**
   * The custom reference.
   *
   * @var string
   */
  protected string $customReference;

  /**
   * The composed reference.
   *
   * @var string|null
   */
  protected ?string $composedReference;

  /**
   * The custom id.
   *
   * @var string
   */
  protected string $customId;

  /**
   * The entity prototype.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Prototypes\EntityPrototype
   */
  protected $prototype;

  /**
   * The mapped data for an object from the HPC API.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   */
  private $children;

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    parent::__construct($data);
    $this->children = [];

    $this->name = ($data->ComposedReference ?? '') . ': ' . $data->Name;
    $this->groupName = ($data->ComposedReference ?? '') . ': ' . $data->Name;
    $this->description = $data->Description ?: NULL;
    $this->entityName = $data->Name;
    $this->planId = $data->PlanId ?? NULL;
    $this->prototypeId = $data->HpcEntityPrototypeId ?? NULL;
    $this->customReference = $data->CustomReference;
    $this->composedReference = $data->ComposedReference ?? NULL;
    // Legacy support.
    $this->customId = $data->CustomReference;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupName(): ?string {
    return $this->groupName ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomName($type) {
    switch ($type) {
      case 'custom_id':
        return $this->getCustomReference();

      case 'custom_id_prefixed_refcode':
        return $this->getEntityTypeRefCode() . $this->getCustomReference();

      case 'composed_reference':
        return $this->getComposedReference();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomReference():string {
    return $this->customReference;
  }

  /**
   * {@inheritdoc}
   */
  public function getComposedReference(): ?string {
    return $this->composedReference;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName(): string {
    return $this->getPluralName();
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren(): array {
    return $this->children ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function addChild(EntityObjectInterface $entity) {
    $this->children[$entity->id()] = $entity;
    ArrayHelper::sortObjectsByStringProperty($this->children, 'sort_key');
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    // Make all tags lowercase.
    return array_map('strtolower', $this->tags ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return lcfirst((new \ReflectionClass($this))->getShortName());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeName() {
    $pieces = preg_split('/(?=[A-Z])/', $this->getEntityType());
    return ucfirst(strtolower(implode(' ', $pieces)));
  }

  /**
   * {@inheritdoc}
   */
  public function getPlanId() {
    return $this->planId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?EntityPrototype {
    if ($this->prototype instanceof EntityPrototype) {
      return $this->prototype;
    }
    if (empty($this->prototypeId)) {
      return NULL;
    }
    return $this->getEntityPrototypeQuery()?->getPrototype($this->prototypeId) ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeRefCode(): ?string {
    return $this->getPrototype()?->getRefCode();
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototypeId(): ?int {
    return $this->getPrototype()?->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototypeName(): ?string {
    return $this->getPrototype()?->getNameSingular();
  }

  /**
   * {@inheritdoc}
   */
  public function getSingularName(): string {
    return $this->getPrototype()?->getNameSingular();
  }

  /**
   * {@inheritdoc}
   */
  public function getPluralName(): string {
    return $this->getPrototype()?->getNamePlural();
  }

  /**
   * {@inheritdoc}
   */
  public function getOrderNumber(): ?int {
    return $this->getPrototype()?->getOrderNumber();
  }

  /**
   * {@inheritdoc}
   */
  public function getSortKey(): ?string {
    return (string) ($this->getPrototype()?->getOrderNumber() ?? '') . ($this->getCustomReference()) ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return parent::toArray() + [
      'ref_code' => $this->getEntityTypeRefCode(),
    ];
  }

}
