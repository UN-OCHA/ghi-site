<?php

namespace Drupal\ghi_sections\Menu;

/**
 * A class for section mega menus.
 */
class SectionMegaMenu {

  /**
   * The label for the mega menu component.
   *
   * @var \Drupal\Component\Render\MarkupInterface|string
   */
  private $label;

  /**
   * The list of nodes for the mega menu.
   *
   * @var array
   */
  private $nodes;

  /**
   * The header for the mega menu component as a render array.
   *
   * @var array
   */
  private $header;

  /**
   * Whether this mega menu is currently active.
   *
   * @var bool
   */
  private $isActive = FALSE;

  /**
   * Construct a SectionDropdown object.
   *
   * @param \Drupal\Component\Render\MarkupInterface|string $label
   *   The label.
   * @param array $nodes
   *   The list of nodes for the dropdown. This must be an associative array,
   *   with the keys as the column headers.
   * @param array $header
   *   An optional render array for the header to be shown as the first element
   *   in the list.
   */
  public function __construct($label, $nodes, $header = NULL) {
    $this->label = $label;
    $this->nodes = $nodes;
    $this->header = $header;
  }

  /**
   * Get the label for the mega menu component.
   *
   * @return \Drupal\Component\Render\MarkupInterface|string
   *   The label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Get the nodes to list in the mega menu.
   *
   * @return array
   *   An associative array of nodes, keys are the group labels.
   */
  public function getNodes() {
    return $this->nodes;
  }

  /**
   * Get the nodes to list in the dropdown.
   *
   * @return array
   *   Get the header as a render array if available.
   */
  public function getHeader() {
    return $this->header;
  }

  /**
   * Set this menu to be active.
   */
  public function setActive() {
    $this->isActive = TRUE;
  }

  /**
   * Check if this menu is active.
   *
   * @return bool
   *   The new status for this menu.
   */
  public function isActive() {
    return $this->isActive;
  }

}
