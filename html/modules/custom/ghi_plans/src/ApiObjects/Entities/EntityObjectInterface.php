<?php

namespace Drupal\ghi_plans\ApiObjects\Entities;

use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;

/**
 * Interface for API entity objects.
 */
interface EntityObjectInterface extends PlanEntityInterface {

  /**
   * Get the composed reference.
   *
   * @return string
   *   The composed reference string.
   */
  public function getComposedReference();

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
  public function getDisplayName();

  /**
   * Get the full name for an object for admin purposes.
   *
   * @return string
   *   The full name.
   */
  public function getFullName();

  /**
   * Get the singular name.
   *
   * @return string
   *   The singular name.
   */
  public function getSingularName(): string;

  /**
   * Get the plural name.
   *
   * @return string
   *   The plural name.
   */
  public function getPluralName(): string;

  /**
   * Get the custom reference.
   *
   * @return string
   *   The custom reference.
   */
  public function getCustomReference():string;

  /**
   * Get the entity type ref code.
   *
   * @return string
   *   The entity type ref code.
   */
  public function getEntityTypeRefCode():string;

  /**
   * Get tags for an entity.
   *
   * @return array
   *   The tags for the entity as retrieved from the API.
   */
  public function getTags();

  /**
   * Get the plan id to which the entity belongs.
   *
   * @return int
   *   The plan id.
   */
  public function getPlanId();

}
