<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for API entity objects.
 */
interface EntityObjectInterface extends ApiObjectInterface {

  /**
   * Get the version property of an API entity object.
   *
   * @return object
   *   The version property object.
   */
  public function getEntityVersion();

  /**
   * Get the children of an entity object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   The child entity objects.
   */
  public function getChildren();

  /**
   * Add a child object to an entity object.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface $entity
   *   The entity object to add as a child.
   */
  public function addChild(EntityObjectInterface $entity);

  /**
   * Get the name for an object for display purposes.
   *
   * @return string
   *   The name.
   */
  public function getEntityName();

  /**
   * Get the full name for an object for admin purposes.
   *
   * @return string
   *   The full name.
   */
  public function getFullName();

  /**
   * Get tags for an entity.
   *
   * @return array
   *   The tags for the entity as retrieved from the API.
   */
  public function getTags();

}
