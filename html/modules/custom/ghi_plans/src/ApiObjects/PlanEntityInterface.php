<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\hpc_api\ApiObjects\ApiObjectInterface;

/**
 * Interface for all possible entities of a plan.
 *
 * This includes the root level plan object as well as any subordinated plan
 * or governing entities.
 */
interface PlanEntityInterface extends ApiObjectInterface {

  /**
   * The Fabric source entity type for plan objects.
   */
  const ENTITY_TYPE_PLAN = 'plan';

  /**
   * The Fabric source entity type for logframe/plan entity objects.
   */
  const ENTITY_TYPE_PLAN_ENTITY = 'planEntity';

  /**
   * The Fabric source entity type for governing entity objects.
   */
  const ENTITY_TYPE_GOVERNING_ENTITY = 'governingEntity';

  /**
   * Get a name for an entity.
   *
   * @return string
   *   The name.
   */
  public function getName();

  /**
   * Get a custom name for an entity, based on $type.
   *
   * @param string $type
   *   The type for the name to be returned.
   *
   * @return string
   *   The name according to $type.
   */
  public function getCustomName($type);

  /**
   * Get the description for an entity.
   *
   * @return string|null
   *   The full name.
   */
  public function getDescription(): ?string;

  /**
   * Get the ref code for the entity type that this entity belongs to.
   *
   * @return string
   *   The ref code as a string, .e.g. SO, CQ, HC, ...
   */
  public function getEntityTypeRefCode();

  /**
   * Get the type name of an entity.
   *
   * @return string
   *   The type as a string.
   */
  public function getTypeName();

  /**
   * Get the machine name of an entity type.
   *
   * @return string
   *   The type as a string.
   */
  public function getEntityType();

  /**
   * Get the human readable name of an entity type.
   *
   * @return string
   *   The type as a string.
   */
  public function getEntityTypeName();

}
