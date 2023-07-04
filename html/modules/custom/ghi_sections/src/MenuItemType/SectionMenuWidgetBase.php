<?php

namespace Drupal\ghi_sections\MenuItemType;

use Drupal\node\NodeInterface;

/**
 * A class for section dropdowns.
 */
abstract class SectionMenuWidgetBase {

  /**
   * The current node for the page.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $currentNode;

  /**
   * Set the current node for the menu item.
   *
   * @param \Drupal\node\NodeInterface $current_node
   *   A node object.
   */
  public function setCurrentNode(NodeInterface $current_node) {
    $this->currentNode = $current_node;
  }

  /**
   * Get the current node for the menu item.
   *
   * @return \Drupal\node\NodeInterface
   *   A node object.
   */
  public function getCurrentNode() {
    return $this->currentNode;
  }

  /**
   * Build a render array for the widget.
   *
   * @return array
   *   A render array representing the section menu widget.
   */
  abstract public function toRenderable();

}
