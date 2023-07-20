<?php

namespace Drupal\ghi_subpages;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_subpages\Entity\SubpageManualInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Subpage manager service class.
 */
class SubpageManager extends BaseObjectSubpageManager {

  /**
   * A list of node bundles that are supported as base types.
   */
  const SUPPORTED_BASE_TYPES = [
    'section',
    'global_section',
    'homepage',
  ];

  /**
   * A list of node bundles that are supported as subpages.
   */
  const SUPPORTED_SUBPAGE_TYPES = [
    'population',
    'financials',
    'presence',
    'logframe',
    'progress',
  ];

  /**
   * Get all available subpage types.
   *
   * @return array
   *   An array of node type machine names.
   */
  public function getStandardSubpageTypes() {
    // The basic subpages defined by this module.
    return self::SUPPORTED_SUBPAGE_TYPES;
  }

  /**
   * Get all available subpage types.
   *
   * @return array
   *   An array of node type machine names.
   */
  public function getSubpageTypes() {
    // The basic subpages defined by this module.
    $subpage_types = self::SUPPORTED_SUBPAGE_TYPES;

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $node_type) {
      if (in_array($node_type->id(), $subpage_types)) {
        continue;
      }
      $is_subpage = FALSE;
      $this->moduleHandler->invokeAllWith('is_subpage_type', function (callable $hook, string $module) use ($node_type, &$is_subpage) {
        // If any module says yes, we accept that.
        $is_subpage = $is_subpage || $hook($node_type->id());
      });
      if ($is_subpage) {
        $subpage_types[] = $node_type->id();
      }
    }
    return $subpage_types;
  }

  /**
   * Load a subpage node for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object for which to load a dedicated subpage.
   *
   * @return \Drupal\node\NodeInterface|null
   *   A node object or NULL.
   */
  public function loadSubpageForBaseObject(BaseObjectInterface $base_object) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $this->getSubpageTypes(),
      'field_base_object' => $base_object->id(),
    ]);
    return count($nodes) == 1 ? reset($nodes) : NULL;
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
  public function loadSubpagesForBaseNode(NodeInterface $node) {
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => SubpageManager::SUPPORTED_SUBPAGE_TYPES,
      'field_entity_reference' => $node->id(),
    ]);
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
  public function getCustomSubpagesForBaseNode(NodeInterface $node, NodeTypeInterface $node_type) {
    $subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $node_type->id(),
      'field_entity_reference' => $node->id(),
    ]);
    $this->moduleHandler->alter('custom_subpages', $subpages, $node, $node_type);
    return $subpages;
  }

  /**
   * Get the corresponding base type node for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The base type node if found.
   */
  public function getBaseTypeNode(NodeInterface $node) {
    if ($this->isBaseTypeNode($node)) {
      return $node;
    }
    if ($this->isSubpageTypeNode($node)) {
      if ($node->hasField('field_entity_reference')) {
        return $node->get('field_entity_reference')->entity;
      }
      $base_type_node = NULL;
      $this->moduleHandler->alter('get_base_type_node', $base_type_node, $node);
      return $base_type_node;
    }
    return NULL;
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
  public function isBaseTypeNode(NodeInterface $node) {
    return in_array($node->bundle(), self::SUPPORTED_BASE_TYPES);
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
  public function isSubpageType(NodeTypeInterface $node_type) {
    $subpage_types = $this->getSubpageTypes();
    return in_array($node_type->id(), $subpage_types);
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
  public function isManualSubpageType(NodeTypeInterface $node_type) {
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo('node');
    $class = $bundle_info[$node_type->id()]['class'] ?? NULL;
    return $class && is_subclass_of($class, SubpageManualInterface::class);
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
  public function isSubpageTypeNode(NodeInterface $node) {
    return $this->isSubpageType($node->type->entity);
  }

  /**
   * Check if the given node type is a standard subpage type.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isStandardSubpageType(NodeTypeInterface $node_type) {
    $standard_subpage_types = self::SUPPORTED_SUBPAGE_TYPES;
    return in_array($node_type->id(), $standard_subpage_types);
  }

  /**
   * Check if the given node is a standard subpage type.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node type to check.
   *
   * @return bool
   *   TRUE if it is a subpage type, FALSE otherwhise.
   */
  public function isStandardSubpageTypeNode(NodeInterface $node) {
    return $this->isStandardSubpageType($node->type->entity);
  }

}
