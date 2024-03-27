<?php

namespace Drupal\ghi_teams\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for teams.
 */
class Team extends Term {

  const BUNDLE = 'team';

  /**
   * Get the content spaces associated to the team.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The associated content space terms if any.
   */
  public function getContentSpaces() {
    if (!$this->hasField('field_content_spaces')) {
      return [];
    }
    return $this->get('field_content_spaces')->referencedEntities();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate() {
    $cache_tags = parent::getCacheTagsToInvalidate();
    $cache_tags = Cache::mergeTags(['config:views.view.content'], $cache_tags);
    $content_spaces = $this->getContentSpaces();
    foreach ($content_spaces as $content_space) {
      $cache_tags = Cache::mergeTags($cache_tags, $content_space->getCacheTags());
    }
    return $cache_tags;
  }

}
