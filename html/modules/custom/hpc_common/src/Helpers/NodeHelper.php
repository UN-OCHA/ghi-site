<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\node\Entity\Node;

/**
 * Helper class for node related things.
 */
class NodeHelper extends EntityHelper {

  /**
   * Load multiple node IDs from it's original IDs.
   *
   * @param array $original_ids
   *   The array with original ids to look up.
   * @param string $bundle
   *   The bundle that the requested nodes belong to.
   *
   * @return array
   *   An array of node IDs.
   */
  public static function getNodeIdsFromOriginalIds(array $original_ids, $bundle) {
    $node_ids = &drupal_static(__FUNCTION__, []);
    $requested_node_ids = array_intersect_key($node_ids, array_flip($original_ids));
    if (count($requested_node_ids) == count($original_ids)) {
      return $requested_node_ids;
    }
    else {
      $result = self::getNodesFromOriginalIds($original_ids, $bundle);
      if (empty($result)) {
        return $result;
      }
      foreach ($result as $entity) {
        $node_ids[self::getFieldData($entity, 'field_original_id', 0, 'value')] = $entity->id();
      }
    }
    return array_intersect_key($node_ids, array_flip($original_ids));
  }

  /**
   * Load multple nodes from it's original IDs.
   *
   * @param array $original_ids
   *   The array with original ids to look up.
   * @param string $bundle
   *   The bundle that the requested nodes belong to.
   *
   * @return array
   *   An array of nodes.
   */
  public static function getNodesFromOriginalIds(array $original_ids, $bundle) {
    if (empty($original_ids) || empty($bundle)) {
      return NULL;
    }
    $nodes = &drupal_static(__FUNCTION__, []);
    if (empty($nodes[$bundle])) {
      $nodes[$bundle] = [];
    }
    $requested_nodes = array_intersect_key($nodes[$bundle], array_flip($original_ids));
    if (count($requested_nodes) == count($original_ids)) {
      return $requested_nodes;
    }
    else {
      $result = \Drupal::entityTypeManager()
        ->getStorage('node')
        ->loadByProperties([
          'type' => $bundle,
          'field_original_id' => $original_ids,
        ]);
      if (empty($result)) {
        return $result;
      }
      foreach ($result as $entity) {
        $nodes[$bundle][self::getFieldData($entity, 'field_original_id', 0, 'value')] = $entity;
      }

    }
    return array_intersect_key($nodes[$bundle], array_flip($original_ids));
  }

  /**
   * Load a node ID from it's original ID.
   *
   * @param int $original_id
   *   The original id to look up.
   * @param string $bundle
   *   The bundle that the requested node belongs to.
   *
   * @return int
   *   The node id if found.
   */
  public static function getNodeIdFromOriginalId($original_id, $bundle) {
    $result = self::getNodeIdsFromOriginalIds([$original_id], $bundle);
    if (is_array($result) && count($result) > 1) {
      return NULL;
    }
    return $result ? reset($result) : NULL;
  }

  /**
   * Retrieve a list of plans by title.
   *
   * @param string $title
   *   String to search for.
   * @param string $bundle
   *   The bundle to look at.
   * @param string $operator
   *   The comparison operator.
   *
   * @return array
   *   An array of matching entity objects.
   */
  public static function getNodesFromTitle($title, $bundle, $operator = 'CONTAINS') {
    $nids = \Drupal::entityQuery('node')
      ->condition('title', $title, $operator)
      ->condition('type', $bundle)
      ->sort('nid', 'DESC')
      ->execute();
    $nodes = Node::loadMultiple($nids);
    $nodes = array_filter($nodes, function ($node) {
      $restricted = self::getFieldProperty($node, 'field_restricted');
      return empty($restricted) || $restricted === FALSE;
    });
    return $nodes;
  }

  /**
   * Load a node from it's original ID.
   *
   * @param int $original_id
   *   The original id to look up.
   * @param string $bundle
   *   The bundle that the requested node belongs to.
   *
   * @return \Drupal\node\Entity\Node
   *   The node object or NULL|FALSE if not found or if found too many.
   */
  public static function getNodeFromOriginalId($original_id, $bundle) {
    $nodes = &drupal_static(__FUNCTION__, []);
    if (empty($nodes[$bundle])) {
      $nodes[$bundle] = [];
    }
    if (empty($nodes[$bundle][$original_id])) {
      $result = self::getNodesFromOriginalIds([$original_id], $bundle);
      if (is_array($result) && count($result) > 1) {
        return NULL;
      }
      $nodes[$bundle][$original_id] = $result ? reset($result) : NULL;
    }
    return $nodes[$bundle][$original_id];
  }

  /**
   * Load an original ID from it's node ID.
   *
   * @param int $nid
   *   The node id for which to look up the original id.
   *
   * @return int
   *   The original id if found.
   */
  public static function getOriginalIdFromNodeId($nid) {
    $node = Node::load($nid);
    return self::getOriginalIdFromNode($node);
  }

  /**
   * Load an original ID for a node.
   *
   * @param \Drupal\node\Entity\ContentEntityBase $node
   *   The node object for which to look up the original id.
   *
   * @return int
   *   The original id if found.
   */
  public static function getOriginalIdFromNode(ContentEntityBase $node) {
    return self::getOriginalIdFromEntity($node);
  }

  /**
   * Get an object title from an original ID.
   */
  public static function getTitleFromOriginalId($original_id, $bundle) {
    $node = self::getNodeFromOriginalId($original_id, $bundle);
    return $node ? $node->getTitle() : NULL;
  }

  /**
   * Get an entity original ID from title.
   */
  public static function getOriginalIdFromTitle($title, $bundle) {
    $query = \Drupal::entityQuery('node');
    $query->condition('title', $title)->condition('type', $bundle);
    $result = $query->execute();
    if (empty($result)) {
      return NULL;
    }
    $nid = reset($result);
    return self::getOriginalIdFromNodeId($nid);
  }

}
