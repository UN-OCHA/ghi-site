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
 * Let modules alter the base type node.
 *
 * @param \Drupal\node\NodeInterface[] $subpages
 *   An array of node objects.
 * @param \Drupal\node\NodeInterface $node
 *   The base node.
 * @param \Drupal\node\NodeTypeInterface $node_type
 *   The node type for the custom subpage to fetch.
 */
function hook_custom_subpages_alter(array &$subpages, NodeInterface $node, NodeTypeInterface $node_type) {
  // Add another custom page to the list of articles.
  if ($node_type->id() == 'article') {
    $subpages[] = Node::load($some_node_id);
  }
}

/**
 * Allow to alter the node that is used as a base type node for the given node.
 *
 * @param \Drupal\node\NodeInterface|null $base_type_node
 *   A base type node or null if non has been determined yet.
 * @param \Drupal\node\NodeInterface $node
 *   The node object for which to find the base node.
 */
function hook_get_base_type_node_alter(&$base_type_node, NodeInterface $node) {
  // Article nodes define the base type in a non-standard reference field.
  if ($node->bundle() == 'article') {
    $base_type_node = $node->get('field_base_reference')->entity;
  }
}

/**
 * Let modules add links to the subtable headers on a sections subpages form.
 *
 * @param \Drupal\node\NodeTypeInterface $node_type
 *   The type of nodes that is shown in subtable.
 * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section_node
 *   The section node of the subpages form.
 *
 * @return \Drupal\Core\Link[]
 *   An array of link objects.
 */
function hook_subpage_admin_form_header_links(NodeTypeInterface $node_type, SectionNodeInterface $section_node) {
  $links = [];
  if ($node_type->id() == 'article') {
    $links['add_article'] = Link::createFromRoute(t('Add new article'), 'node.add', [
      'node_type' => $node_type->id(),
    ]);
  }
  return $links;
}
