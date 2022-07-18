<?php

namespace Drupal\ghi_plan_clusters;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_subpages\BaseObjectSubpageManager;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;

/**
 * Plan cluster manager service class.
 */
class PlanClusterManager extends BaseObjectSubpageManager {

  use LayoutEntityHelperTrait;
  use StringTranslationTrait;

  /**
   * The machine name of the bundle to use for plan clusters.
   */
  const NODE_BUNDLE_PLAN_CLUSTER = 'plan_cluster';

  /**
   * The machine name of the base object bundle for governing entities.
   */
  const BASE_OBJECT_BUNDLE_GOVERNING_ENTITY = 'governing_entity';

  /**
   * Load a cluster subpage for the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\node\NodeInterface
   *   The subpage node.
   */
  public function loadClusterSubpageForBaseObject(BaseObjectInterface $base_object) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::NODE_BUNDLE_PLAN_CLUSTER,
      'field_base_object' => $base_object->id(),
    ]);
    return !empty($nodes) ? reset($nodes) : NULL;
  }

  /**
   * Load the governing entity base objects for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The section that plan clusters belong to.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]|null
   *   A list of base objects.
   */
  public function loadGoverningEntityBaseObjectsForSection(NodeInterface $node) {
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    if (!$base_object || $base_object->bundle() != 'plan') {
      // We only support plan sections for now.
      return NULL;
    }

    // Now find all governing entity base objects that reference the plan base
    // object.
    return $this->entityTypeManager->getStorage('base_object')->loadByProperties([
      'type' => self::BASE_OBJECT_BUNDLE_GOVERNING_ENTITY,
      'field_plan' => $base_object->id(),
    ]);
  }

  /**
   * Load all plan cluster nodes for a section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section that plan clusters belong to.
   *
   * @return \Drupal\node\NodeInterface[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForSection(NodeInterface $section) {
    if ($section->bundle() != 'section') {
      return NULL;
    }

    // Now find all governing entity base objects that reference the plan base
    // object.
    $governing_entities = $this->loadGoverningEntityBaseObjectsForSection($section);
    if (empty($governing_entities)) {
      return NULL;
    }

    $plan_clusters = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::NODE_BUNDLE_PLAN_CLUSTER,
      'field_base_object' => array_map(function (BaseObjectInterface $base_object) {
        return $base_object->id();
      }, $governing_entities),
    ]);
    return !empty($plan_clusters) ? $plan_clusters : NULL;
  }

  /**
   * Assure that cluster subpages for a base node exist.
   *
   * If they don't exist, this function will create the missing ones.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function assureSubpagesForBaseNode(NodeInterface $node) {
    if (!SubpageHelper::isBaseTypeNode($node)) {
      return;
    }

    $parent_node = $this->entityTypeManager->getStorage('node')->load($node->id());
    $governing_entities = $this->loadGoverningEntityBaseObjectsForSection($parent_node);
    if (empty($governing_entities)) {
      return NULL;
    }

    foreach ($governing_entities as $governing_entity) {
      $cluster_subpage = $this->loadClusterSubpageForBaseObject($governing_entity);
      if ($cluster_subpage) {
        continue;
      }

      /** @var \Drupal\node\NodeInterface $subpage */
      $subpage = Node::create([
        'type' => self::NODE_BUNDLE_PLAN_CLUSTER,
        'title' => $governing_entity->label(),
        'uid' => $parent_node->uid,
        'status' => NodeInterface::NOT_PUBLISHED,
        'field_base_object' => [
          'target_id' => $governing_entity->id(),
        ],
      ]);

      $subpage->save();
      $this->messenger->addStatus($this->t('Created cluster subpage @type for @title', [
        '@type' => $governing_entity->label(),
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
    if (!SubpageHelper::isBaseTypeNode($node)) {
      return;
    }
    $governing_entities = $this->loadGoverningEntityBaseObjectsForSection($node);
    if (empty($governing_entities)) {
      return NULL;
    }
    foreach ($governing_entities as $governing_entity) {
      $cluster_subpage = $this->loadClusterSubpageForBaseObject($governing_entity);
      if (!$cluster_subpage) {
        continue;
      }
      $cluster_subpage->delete();
      $this->messenger->addStatus($this->t('Deleted cluster subpage @type for @title', [
        '@type' => $cluster_subpage->label(),
        '@title' => $node->label(),
      ]));
    }
  }

  /**
   * Load the plan section for the given plan cluster node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The plan cluster node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The section node if found.
   */
  public function loadSectionForClusterNode(NodeInterface $node) {
    if ($node->bundle() != PlanClusterManager::NODE_BUNDLE_PLAN_CLUSTER) {
      return NULL;
    }
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    if (!$base_object || $base_object->bundle() != 'governing_entity') {
      // We only support plan sections for now.
      return NULL;
    }

    $plan_object = $base_object->get('field_plan')->entity;
    return $this->sectionManager->loadSectionForBaseObject($plan_object);
  }

}
