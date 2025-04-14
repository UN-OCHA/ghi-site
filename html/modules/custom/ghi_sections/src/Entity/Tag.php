<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\taxonomy\Entity\Term;

/**
 * Bundle class for teams.
 */
class Tag extends Term {

  const BUNDLE = 'tags';

  /**
   * Get the type of tag.
   *
   * @return string|null
   *   The type of tag if any.
   */
  public function getType() {
    if (!$this->hasField('field_type')) {
      return NULL;
    }
    return $this->get('field_type')->value;
  }

}
