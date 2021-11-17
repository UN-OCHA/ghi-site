<?php

namespace Drupal\ghi_subpages\Helpers;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Helper class for subpages.
 */
class SubpageHelper {

  /**
   * A list of node bundles that are supported as base types.
   */
  const SUPPORTED_BASE_TYPES = [
    'section',
    'global_section',
  ];

  /**
   * A list of node bundles that are supported as subpages.
   */
  const SUPPORTED_SUBPAGE_TYPES = [
    'profile',
    'population',
    'financials',
    'risk_index',
  ];

  /**
   * Assure that subpages for a base node exist.
   *
   * If they don't exist, this function will create the missing ones.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public static function assureSubpagesForBaseNode(NodeInterface $node) {
    if (!in_array($node->getType(), self::SUPPORTED_BASE_TYPES)) {
      return;
    }

    $parent_node = Node::load($node->id());

    foreach (self::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      if (self::getSubpageForBaseNode($node, $subpage_type)) {
        continue;
      }

      /** @var \Drupal\node\Entity\NodeTypeInterface $node_type */
      $node_type = \Drupal::entityTypeManager()->getStorage('node_type')->load($subpage_type);
      $subpage_name = $node_type->get('name');
      /** @var \Drupal\node\NodeInterface $subpage */
      $subpage = Node::create([
        'type' => $subpage_type,
        'title' => $subpage_name,
        'uid' => $parent_node->uid,
        'status' => NodeInterface::NOT_PUBLISHED,
        'field_entity_reference' => [
          'target_id' => $parent_node->id(),
        ],
      ]);

      $subpage->save();
      \Drupal::messenger()->addStatus(t('Created @type subpage for @title', [
        '@type' => $subpage_name,
        '@title' => $parent_node->getTitle(),
      ]));
    }
  }

  /**
   * Delete all subpages for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public static function deleteSubpagesForBaseNode(NodeInterface $node) {
    if (!in_array($node->getType(), self::SUPPORTED_BASE_TYPES)) {
      return;
    }
    foreach (self::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $subpage_node = self::getSubpageForBaseNode($node, $subpage_type);
      if (!$subpage_node) {
        continue;
      }
      $subpage_node->delete();
      \Drupal::messenger()->addStatus(t('Deleted @type subpage for @title', [
        '@type' => $subpage_node->getTitle(),
        '@title' => $node->getTitle(),
      ]));
    }
  }

  /**
   * Get all subpage nodes for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of subpage nodes if found, NULL otherwhise.
   */
  public static function getSubpagesForBaseNode(NodeInterface $node) {
    return \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'field_entity_reference' => $node->id(),
    ]);
  }

  /**
   * Get the subpage node for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param string $subpage_type
   *   A subpage type.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A subpage node if found, NULL otherwhise.
   */
  public static function getSubpageForBaseNode(NodeInterface $node, $subpage_type) {
    $matching_subpages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'type' => $subpage_type,
      'field_entity_reference' => $node->id(),
    ]);
    return !empty($matching_subpages) ? reset($matching_subpages) : NULL;
  }

}
