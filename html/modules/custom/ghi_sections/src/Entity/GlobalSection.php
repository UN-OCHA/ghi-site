<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\node\Entity\Node;

/**
 * Bundle class for global section nodes.
 */
class GlobalSection extends Node implements SectionNodeInterface {

  /**
   * {@inheritdoc}
   */
  public function getPageTitle() {
    return $this->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getImage() {
    return $this->get('field_hero_image');
  }

}
