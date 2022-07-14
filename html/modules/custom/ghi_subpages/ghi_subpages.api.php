<?php

/**
 * @file
 * Hooks provided by GHI Subpages module.
 */

/**
 * Alter the link tree structure for the subpage navigation.
 *
 * @param array $links
 *   A nested array structure containing links.
 * @param array $context
 *   An associative array containing objects that define the context.
 */
function hook_subpage_navigation_links_alter(array &$links, array $context) {
  // Add a custom class to the main link wrapper.
  $links['#attributes']['class'][] = 'custom-class';
}

/**
 * Let modules define a node type as a valid subpage type.
 *
 * @param string $node_type
 *   The node type.
 *
 * @return bool
 *   TRUE if the given node should be considered a subpage, FALSE otherwise.
 */
function hook_is_subpage_type($node_type) {
  // Add a custom class to the main link wrapper.
  return in_array($node_type, ['custom_type']);
}

/**
 * Implements hook_custom_subpages_alter().
 */
function hook_custom_subpages_alter(&$subpages, $node, $node_type) {

}
