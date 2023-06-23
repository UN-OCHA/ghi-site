<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for section nodes.
 */
abstract class ContentBase extends Node implements NodeInterface {

  /**
   * Get the meta data for this article.
   *
   * @return array
   *   An array of metadata items.
   */
  abstract public function getPageMetaData();

  /**
   * Get the tags for display.
   *
   * @param int $limit
   *   How many tags to return at most.
   *
   * @return array
   *   An array of tag names.
   */
  public function getDisplayTags($limit = 6) {
    $cache_tags = [];

    // Get the tags.
    $tags = $this->get('field_tags')->referencedEntities();
    $structural_tags = $this->getStructuralTags();
    $structural_tag_ids = array_map(function ($term) {
      return $term->id();
    }, $structural_tags);

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) use (&$cache_tags, $structural_tag_ids) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $cache_tags = Cache::mergeTags($cache_tags, $tag->getCacheTags());
      return !in_array($tag->id(), $structural_tag_ids);
    });

    // Limit number of tags.
    if ($limit > 0 && count($tags) > $limit) {
      $tags = array_slice($tags, 0, $limit);
    }

    // Turn the array of objects into an array of names.
    $tag_names = array_map(function ($tag) {
      return $tag->label();
    }, $tags);

    // And build the render array.
    return $tag_names;
  }

  /**
   * Get the structural tags for an article.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   An array of term entities.
   */
  public function getStructuralTags() {
    // Get the tags.
    $tags = $this->get('field_tags')->referencedEntities();

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $is_structural_tag = (bool) $tag->get('field_structural_tag')?->value ?? FALSE;
      return $is_structural_tag;
    });

    return $tags;
  }

  /**
   * Get the date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  protected function getDateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
