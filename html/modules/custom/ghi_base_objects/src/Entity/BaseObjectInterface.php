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
   * Gets the Base object short name if available.
   *
   * @return string|null
   *   Short name of the Base object if available.
   */
  public function getShortName();

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
   * Get the original id of the base object from the source.
   *
   * @return int|null
   *   The original id or NULL.
   */
  public function getSourceId();

  /**
   * Get the unique identifier of the base object.
   *
   * @return string|null
   *   The original id or NULL.
   */
  public function getUniqueIdentifier();

  /**
   * Check if an object equals another one.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The bae object to compare against.
   *
   * @return bool
   *   TRUE if considered equal, FALSE otherwise.
   */
  public function equals(BaseObjectInterface $base_object);

  /**
   * Check if this base object needs a year for data display.
   *
   * @return bool
   *   Whether this base object needs a year to function.
   */
  public function needsYear();

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

  /**
   * Returns the cache tags that should be used to invalidate API caches.
   *
   * @return string[]
   *   Set of cache tags.
   */
  public function getApiCacheTagsToInvalidate();

}
