<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Base class for API entity objects.
 */
abstract class EntityObjectBase extends ApiObjectBase implements EntityObjectInterface {

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
  public function getChildren() {
    return $this->children;
  }

  /**
   * {@inheritdoc}
   */
  public function addChild($entity) {
    $this->children[$entity->id] = $entity;
    ArrayHelper::sortObjectsByStringProperty($this->children, 'sort_key', EndpointQuery::SORT_ASC);
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    // Make all tags lowercase.
    return array_map('strtolower', $this->tags);
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

}
