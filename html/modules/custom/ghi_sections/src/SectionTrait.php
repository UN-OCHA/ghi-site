<?php

namespace Drupal\ghi_sections;

use Drupal\ghi_subpages\Helpers\SubpageHelper;
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
    return in_array($node->bundle(), SectionManager::SECTION_BUNDLES);
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
    if (in_array($node->bundle(), SectionManager::SECTION_BUNDLES)) {
      return $node;
    }
    if ($node->hasField('field_entity_reference') && in_array($node->bundle(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
      return $node->get('field_entity_reference')->entity;
    }
  }

}
