<?php

/**
 * @file
 * Hooks provided by the GHI Sections module.
 */

use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Alter the ordered section-menu plugin definitions for a section.
 *
 * @param array $definitions
 *   The plugin definitions, keyed by plugin ID and initially ordered by their
 *   configured weights.
 * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
 *   The section for which the definitions will be instantiated.
 */
function hook_ghi_section_menu_items_for_section_alter(array &$definitions, SectionNodeInterface $section): void {
}

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
