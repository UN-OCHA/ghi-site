<?php

namespace Drupal\ghi_subpages;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_sections\SectionTrait;
use Drupal\ghi_subpages\Entity\SubpageManualInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Subpage manager service class.
 */
class SubpageManager extends BaseSubpageManager {

  use SectionTrait;

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
    // Load the basic subpages defined by this module, but make sure they
    // really exist.
    $node_types = $this->entityTypeManager->getStorage('node_type')->loadByProperties([
      'name' => self::SUPPORTED_SUBPAGE_TYPES,
    ]);
    return array_keys($node_types);
  }

  /**
   * Get all available subpage types.
   *
   * @return array
   *   An array of node type machine names.
   */
  public function getSubpageTypes() {
    // The basic subpages defined by this module.
    $subpage_types = [];
    $default_subpage_types = self::SUPPORTED_SUBPAGE_TYPES;

    $node_types = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($node_types as $node_type) {
      if (in_array($node_type->id(), $default_subpage_types)) {
        $subpage_types[] = $node_type->id();
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
   * Assure that subpages for a base node exist.
   *
   * If they don't exist, this function will create the missing ones.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function assureSubpagesForBaseNode(NodeInterface $node) {
    if (!$this->isBaseTypeNode($node)) {
      return;
    }

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $node_type_storage = $this->entityTypeManager->getStorage('node_type');
    $parent_node = $node_storage->load($node->id());

    foreach ($this->getStandardSubpageTypes() as $subpage_type) {
      if ($this->getSubpageForBaseNode($node, $subpage_type)) {
        continue;
      }

      /** @var \Drupal\node\NodeTypeInterface $node_type */
      $node_type = $node_type_storage->load($subpage_type);
      $subpage_name = $node_type->get('name');
      /** @var \Drupal\node\NodeInterface $subpage */
      $subpage = $node_storage->create([
        'type' => $subpage_type,
        'title' => $subpage_name,
        'uid' => $parent_node->uid,
        'status' => NodeInterface::NOT_PUBLISHED,
        'field_entity_reference' => [
          'target_id' => $parent_node->id(),
        ],
      ]);
      $subpage->save();

      $this->messenger->addStatus($this->t('Created @type subpage for @title', [
        '@type' => $subpage_name,
        '@title' => $parent_node->label(),
      ]));
    }
  }

  /**
   * Delete all subpages for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function deleteSubpagesForBaseNode(NodeInterface $node) {
    if (!$this->isBaseTypeNode($node)) {
      return;
    }
    foreach (self::SUPPORTED_SUBPAGE_TYPES as $subpage_type) {
      $subpage_node = $this->getSubpageForBaseNode($node, $subpage_type);
      if (!$subpage_node) {
        continue;
      }
      $subpage_node->delete();
      $this->messenger->addStatus($this->t('Deleted @type subpage for @title', [
        '@type' => $subpage_node->getTitle(),
        '@title' => $node->getTitle(),
      ]));
    }
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
  public function getSubpageForBaseNode(NodeInterface $node, $subpage_type) {
    $matching_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $subpage_type,
      'field_entity_reference' => $node->id(),
    ]);
    return !empty($matching_subpages) ? reset($matching_subpages) : NULL;
  }

  /**
   * Get all subpage nodes for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type for the custom subpage to fetch.
   *
   * @return \Drupal\ghi_subpages\Entity\SubpageNodeInterface[]|null
   *   An array of subpage nodes if found, NULL otherwhise.
   */
  public function getCustomSubpagesForBaseNode(NodeInterface $node, NodeTypeInterface $node_type) {
    $subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => $node_type->id(),
      'field_entity_reference' => $node->id(),
    ]);
    $this->moduleHandler->alter('custom_subpages', $subpages, $node, $node_type);
    $subpages = array_filter($subpages, function ($subpage) {
      return $subpage instanceof SubpageNodeInterface;
    });
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
        $base_type_node = $node->get('field_entity_reference')->entity;
      }
      $this->moduleHandler->alter('get_base_type_node', $base_type_node, $node);
      return $base_type_node;
    }
    return NULL;
  }

  /**
   * Get the label for the section overview page.
   *
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $node
   *   The base node.
   *
   * @return string|null
   *   The label of a section overview page.
   */
  public function getSectionOverviewLabel(SectionNodeInterface $node) {
    $base_object = $node->getBaseObject();
    if ($base_object) {
      return $this->t('@type overview', [
        '@type' => $base_object->type->entity->label(),
      ]);
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
    return $node instanceof SectionNodeInterface;
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
