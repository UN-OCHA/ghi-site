<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\node\NodeInterface;

/**
 * Interface for section nodes.
 */
interface SectionNodeInterface extends NodeInterface {

  const BUNDLE = 'section';
  const BASE_OBJECT_FIELD_NAME = 'field_base_object';

  /**
   * Get the title to be used in the section switcher.
   *
   * @return string
   *   The title to be used in the section switcher.
   */
  public function getSectionSwitcherTitle();

  /**
   * Get the label to be used in the section switcher options.
   *
   * @return array
   *   An array with 2 key-value pairs, one for the label and one for the long
   *   label to be used for disambiguation.
   */
  public function getSectionSwitcherOption();

  /**
   * Get the page title.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The markup object for the title.
   */
  public function getPageTitle();

  /**
   * Get the page metadata.
   *
   * @return array
   *   An array of metadata items.
   */
  public function getPageTitleMetaData();

  /**
   * Get the tags associated to the section.
   *
   * @return array
   *   An array of tag names, keyed by the tag id.
   */
  public function getTags();

  /**
   * Get the tags associated to the section.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of tag terms, keyed by the tag id.
   */
  public function getTagEntities();

  /**
   * Get the base object for a section.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   The base object set for this section node or NULL.
   */
  public function getBaseObject();

  /**
   * Get the section type based on the linked base object type.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The type label of the base object linked to the section.
   */
  public function getSectionType();

  /**
   * Returns the cache tags that should be used to invalidate API caches.
   *
   * @return string[]
   *   Set of cache tags.
   */
  public function getApiCacheTagsToInvalidate();

  /**
   * Check if the entity is currently protected.
   *
   * @return bool
   *   TRUE if the entity is currently protected, FALSE otherwise.
   */
  public function isProtected();

}
