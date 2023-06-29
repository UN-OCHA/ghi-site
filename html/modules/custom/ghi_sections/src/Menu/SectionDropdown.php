<?php

namespace Drupal\ghi_sections\Menu;

/**
 * A class for section dropdowns.
 */
class SectionDropdown {

  /**
   * The label for the dropdown component.
   *
   * @var \Drupal\Component\Render\MarkupInterface|string
   */
  private $label;

  /**
   * The list of nodes for the dropdown.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  private $nodes;

  /**
   * The header label for the dropdown component.
   *
   * @var \Drupal\Core\Link
   */
  private $headerLink;

  /**
   * Construct a SectionDropdown object.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $label
   *   The label.
   * @param \Drupal\node\NodeInterface[] $nodes
   *   The list of nodes for the dropdown.
   * @param \Drupal\Core\Link $header_link
   *   An optional header link to be shown as the first element in the list.
   */
  public function __construct($label, array $nodes, $header_link = NULL) {
    $this->label = $label;
    $this->nodes = $nodes;
    $this->headerLink = $header_link;
  }

  /**
   * Get the label for the dropdown component.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of nodes.
   */
  public function getNodes() {
    return $this->nodes;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return \Drupal\Core\Link
   *   Get the header link if available.
   */
  public function getHeaderLink() {
    return $this->headerLink;
  }

}
