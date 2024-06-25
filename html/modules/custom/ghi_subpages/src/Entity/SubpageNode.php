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
   * {@inheritdoc}
   */
  public function getParentNode() {
    $entity = $this->get('field_entity_reference')->entity;
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getParentBaseNode() {
    $entity = $this->getParentNode();
    if ($entity instanceof SectionNodeInterface) {
      return $entity;
    }
    if ($entity instanceof SubpageNodeInterface) {
      return $entity->getParentNode();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    $parent = $this->getParentBaseNode();
    if ($parent) {
      Cache::invalidateTags($parent->getCacheTags());
    }
  }

}
