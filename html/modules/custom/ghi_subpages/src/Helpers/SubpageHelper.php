<?php

namespace Drupal\ghi_subpages\Helpers;

use Drupal\ghi_subpages\SubpageManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Helper class for subpages.
 */
class SubpageHelper {

  /**
   * Get the subpage manager.
   *
   * @return \Drupal\ghi_subpages\SubpageManager
   *   The subpage manager class.
   */
  private static function getSubpageManager() {
    return \Drupal::service('ghi_subpages.manager');
  }

  /**
   * Get the label for the section overview page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   *
   * @return string|null
   *   The label of a section overview page.
   */
  public static function getSectionOverviewLabel(NodeInterface $node) {
    if (!self::isBaseTypeNode($node)) {
      return;
    }
    if ($node->field_base_object) {
      return t('@type overview', [
        '@type' => $node->field_base_object->entity->type->entity->label(),
      ]);
    }
    return NULL;
  }

  /**
   * Assure that subpages for a base node exist.
   *
   * If they don't exist, this function will create the missing ones.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public static function assureSubpagesForBaseNode(NodeInterface $node) {
    if (!self::isBaseTypeNode($node)) {
      return;
    }

    $parent_node = Node::load($node->id());

    foreach (self::getSubpageTypes() as $subpage_type) {
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
      if (PHP_SAPI !== 'cli') {
        \Drupal::messenger()->addStatus(t('Created @type subpage for @title', [
          '@type' => $subpage_name,
          '@title' => $parent_node->getTitle(),
        ]));
      }
    }
  }

  /**
   * Delete all subpages for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public static function deleteSubpagesForBaseNode(NodeInterface $node) {
    if (!self::isBaseTypeNode($node)) {
      return;
    }
    foreach (SubpageManager::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
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
   * Get all available subpage types.
   *
   * @return string[]
   *   An array of node type machine names.
   */
  public static function getSubpageTypes() {
    return self::getSubpageManager()->getSubpageTypes();
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
  public static function loadSubpagesForBaseNode(NodeInterface $node) {
    return self::getSubpageManager()->loadSubpagesForBaseNode($node);
  }

  /**
   * Get all subpage nodes for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type for the custom subpage to fetch.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of subpage nodes if found, NULL otherwhise.
   */
  public static function getCustomSubpagesForBaseNode(NodeInterface $node, NodeTypeInterface $node_type) {
    return self::getSubpageManager()->getCustomSubpagesForBaseNode($node, $node_type);
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

  /**
   * Get the corresponding base type node for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\ghi_sections\Entity\SectionNodeInterface|null
   *   The base type node if found.
   */
  public static function getBaseTypeNode(NodeInterface $node) {
    return self::getSubpageManager()->getBaseTypeNode($node);
  }

  /**
   * Check if the given node is a base type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if it is a base type, FALSE otherwhise.
   */
  public static function isBaseTypeNode(NodeInterface $node) {
    return self::getSubpageManager()->isBaseTypeNode($node);
  }

  /**
   * Check if the given node type is a subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public static function isSubpageType(NodeTypeInterface $node_type) {
    return self::getSubpageManager()->isSubpageType($node_type);
  }

  /**
   * Check if the given node type is a manual subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a manual subpage type, FALSE otherwhise.
   */
  public static function isManualSubpageType(NodeTypeInterface $node_type) {
    return self::getSubpageManager()->isManualSubpageType($node_type);
  }

  /**
   * Check if the given node is a subpage type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to check.
   *
   * @return bool
   *   TRUE if it is a subpage type node, FALSE otherwhise.
   */
  public static function isSubpageTypeNode(NodeInterface $node) {
    return self::getSubpageManager()->isSubpageType($node->type->entity);
  }

}
