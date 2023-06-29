<?php

/**
 * @file
 * Hooks provided by GHI Subpages module.
 */

use Drupal\ghi_sections\Entity\Section;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Alter the current section.
 *
 * @param \Drupal\ghi_sections\Entity\Section|null $section
 *   The section determined for the current page so far.
 * @param \Drupal\node\NodeInterface $node
 *   The main node object for the current page.
 */
function hook_current_section_alter(Section &$section, NodeInterface $node) {
  $section = Node::load(1);
}

/**
 * Add standard subpage nodes for the given section.
 *
 * @param \Drupal\ghi_sections\Entity\Section $section
 *   The section node.
 *
 * @return array
 *   An aray of objects, either \Drupal\node\NodeInterface or
 *   \Drupal\ghi_sections\Menu\SectionDropdown.
 */
function hook_section_standard_subpage_nodes(Section $section) {
  return [];
}

/**
 * Add non-standard subpage nodes for the given section.
 *
 * @param \Drupal\ghi_sections\Entity\Section $section
 *   The section node.
 *
 * @return array
 *   An aray of objects, either \Drupal\node\NodeInterface or
 *   \Drupal\ghi_sections\Menu\SectionDropdown.
 */
function hook_section_subpage_nodes(Section $section) {
  return [];
}
