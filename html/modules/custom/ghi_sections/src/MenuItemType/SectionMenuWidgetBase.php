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
   * The label to be used for the widget.
   *
   * @var string
   */
  protected $label;

  /**
   * Whether this mega menu is currently protected.
   *
   * @var bool
   */
  private $protected = FALSE;

  /**
   * An array of cache tags to associate to the widget.
   *
   * @var array
   */
  protected $cacheTags;

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
   * Get the label of the menu item.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   Return the label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Set the label of the menu item.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   Return the label.
   */
  public function setLabel($label) {
    $this->label = $label;
  }

  /**
   * Set this menu to be protected.
   *
   * @param bool $state
   *   The new status for this menu.
   */
  public function setProtected($state = FALSE) {
    $this->protected = $state;
  }

  /**
   * Check if this menu is protected.
   *
   * @return bool
   *   The new status for this menu.
   */
  public function isProtected() {
    return $this->protected;
  }

  /**
   * Set the cache tags for the menu item.
   *
   * @param string[] $cache_tags
   *   The cache tags.
   */
  public function setCacheTags(array $cache_tags) {
    $this->cacheTags = $cache_tags;
  }

  /**
   * Get the cache tags for the menu item.
   *
   * @return string[]
   *   The cache tags.
   */
  public function getCacheTags() {
    return $this->cacheTags ?? [];
  }

  /**
   * Build a render array for the widget.
   *
   * @return array
   *   A render array representing the section menu widget.
   */
  abstract public function toRenderable();

}
