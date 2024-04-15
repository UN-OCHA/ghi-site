<?php

namespace Drupal\ghi_templates\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a page template entity.
 */
interface PageTemplateInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityPublishedInterface {

  /**
   * Setup the template.
   */
  public function setupTemplate();

  /**
   * Get the source entity.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The content entity that was the source for the template.
   */
  public function getSourceEntity();

  /**
   * Get the associated base objects if any.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   The base objects associated with the template.
   */
  public function getBaseObjects();

  /**
   * Set the associated base objects.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_object
   *   The base objects associated with the template.
   */
  public function setBaseObjects(array $base_object);

  /**
   * Get base objects from the source.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   The base objects associated with the source page.
   */
  public function getBaseObjectsfromSource();

  /**
   * Get a summary description for the template source.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $source_template
   *   The markup to use for the source template.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   A summary of the template source, listing the source page and an
   *   associated base object if available.
   */
  public function getSourceSummary($source_template = NULL);

}
