<?php

namespace Drupal\ghi_sections;

use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\NodeInterface;

/**
 * Trait for working with section nodes.
 */
trait SectionTrait {

  /**
   * Check if the given node represents a section.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool
   *   TRUE if the node is a section, FALSE otherwise.
   */
  public function isSectionNode(NodeInterface $node) {
    return $node instanceof SectionNodeInterface;
  }

  /**
   * Get the corresponding section node for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The section node if found.
   */
  public function getSectionNode(NodeInterface $node) {
    if ($this->isSectionNode($node)) {
      return $node;
    }
    if ($node->hasField('field_entity_reference') && in_array($node->bundle(), SubpageManager::SUPPORTED_SUBPAGE_TYPES)) {
      return $node->get('field_entity_reference')->entity;
    }
  }

}
