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
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->map->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupName(): ?string {
    return $this->map->group_name ?? NULL;
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
    return $this->custom_reference;
  }

  /**
   * {@inheritdoc}
   */
  public function getComposedReference() {
    return $this->composed_reference;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName() {
    return $this->getPluralName();
  }

  /**
   * {@inheritdoc}
   */
  public function getChildren() {
    return $this->children;
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
    return $this->getRawData()->PlanId ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPrototype(): ?EntityPrototype {
    if ($this->prototype instanceof EntityPrototype) {
      return $this->prototype;
    }
    $prototype_id = $this->getRawData()->HpcEntityPrototypeId;
    if (empty($prototype_id)) {
      return NULL;
    }
    return $this->getEntityPrototypeQuery()?->getPrototype($prototype_id) ?? NULL;
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
