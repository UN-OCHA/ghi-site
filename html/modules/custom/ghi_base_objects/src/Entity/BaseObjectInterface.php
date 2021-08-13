<?php

namespace Drupal\ghi_base_objects\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Base object entities.
 *
 * @ingroup ghi_base_objects
 */
interface BaseObjectInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the Base object name.
   *
   * @return string
   *   Name of the Base object.
   */
  public function getName();

  /**
   * Sets the Base object name.
   *
   * @param string $name
   *   The Base object name.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The called Base object entity.
   */
  public function setName($name);

  /**
   * Gets the Base object creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Base object.
   */
  public function getCreatedTime();

  /**
   * Sets the Base object creation timestamp.
   *
   * @param int $timestamp
   *   The Base object creation timestamp.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
   *   The called Base object entity.
   */
  public function setCreatedTime($timestamp);

}
