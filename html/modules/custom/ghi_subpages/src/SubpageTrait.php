<?php

namespace Drupal\ghi_subpages;

use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Trait for working with subpage nodes.
 */
trait SubpageTrait {

  /**
   * Check if the given node represents a base type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool
   *   TRUE if the node is a section, FALSE otherwise.
   */
  public function isBaseTypeNode(NodeInterface $node) {
    return SubpageHelper::isBaseTypeNode($node);
  }

  /**
   * Check if the given node represents a section.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return bool
   *   TRUE if the node is a section, FALSE otherwise.
   */
  public function isSubpageTypeNode(NodeInterface $node) {
    return SubpageHelper::isSubpageTypeNode($node);
  }

  /**
   * Check if the given node type is a subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isSubpageType(NodeTypeInterface $node_type) {
    return SubpageHelper::isSubpageType($node_type);
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
  public function getBaseTypeNode(NodeInterface $node) {
    return SubpageHelper::getBaseTypeNode($node);
  }

}
