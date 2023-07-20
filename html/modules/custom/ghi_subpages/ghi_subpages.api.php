<?php

/**
 * @file
 * Hooks provided by GHI Subpages module.
 */

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
function hook_custom_subpages_alter(array &$subpages, NodeInterface $node, NodeTypeInterface $node_type) {
  // Declare the "articles" node type as a valid subpage type.
  $subpages[] = 'articles';
}

/**
 * Allow to alter the node that is used as a base type node for the given node.
 */
function ghi_plan_clusters_get_base_type_node_alter(&$base_type_node, NodeInterface $node) {
  // Article nodes define the base type in a non-standard reference field.
  if ($node->bundle() == 'article') {
    $base_type_node = $node->get('field_base_reference')->entity;
  }
}
