<?php

namespace Drupal\ghi_subpages\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\Entity\Node;

/**
 * Base class for subpage nodes.
 */
abstract class SubpageNode extends Node implements SubpageNodeInterface {

  /**
   * Get the parent node.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface
   *   The parent node.
   */
  public function getParentNode() {
    $entity = $this->get('field_entity_reference')->entity;
    return $entity instanceof SectionNodeInterface ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $parent = $this->getParentNode();
    if ($parent) {
      Cache::invalidateTags($parent->getCacheTags());
    }
  }

}
