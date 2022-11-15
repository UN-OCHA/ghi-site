<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Render\Markup;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Bundle class for section nodes.
 */
class Article extends Node implements NodeInterface {

  /**
   * Get the chapter of the article.
   *
   * @return string|null
   *   The chapter as a plain string or NULL.
   */
  public function getChapter() {
    return $this->get('field_chapter')->value ?? NULL;
  }

  /**
   * Get the tags for display.
   *
   * @param int $limit
   *   How many tags to return at most.
   *
   * @return array
   *   A render array.
   */
  public function getDisplayTags($limit = 6) {
    $cache_tags = [];

    // Get the tags.
    $tags = $this->get('field_tags')->referencedEntities();

    // Filter out the structural tags.
    $tags = array_filter($tags, function ($tag) use (&$cache_tags) {
      /** @var \Drupal\taxonomy\TermInterface $tag */
      $cache_tags = Cache::mergeTags($cache_tags, $tag->getCacheTags());
      $is_structural_tag = (bool) $tag->get('field_structural_tag')?->value ?? FALSE;
      return !$is_structural_tag;
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
    return [
      '#markup' => Markup::create(implode(', ', $tag_names)),
      '#cache' => [
        'tags' => $cache_tags,
      ],
    ];
  }

}
