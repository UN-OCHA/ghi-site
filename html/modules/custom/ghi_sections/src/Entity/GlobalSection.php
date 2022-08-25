<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
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

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Make sure we have a valid alias for this node.
    \Drupal::service('pathauto.generator')->updateEntityAlias($this, $this->isNew() ? 'insert' : 'update');

    // Due to the way that the pathauto module works, the alias generation for
    // the subpages is not finished at this point. This is because at the time
    // when each subpage gets created and it's alias build, the section alias
    // itself has not been build, so that token replacements are not fully
    // available. To fix this, we invoke a custom hook that lets the
    // GHI Subpages module react just after a section has been fully build.
    \Drupal::moduleHandler()->invokeAll('section_post_save', [$this]);
  }

}
