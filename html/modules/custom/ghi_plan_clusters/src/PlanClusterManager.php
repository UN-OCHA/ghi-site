<?php

namespace Drupal\ghi_plan_clusters;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_plan_clusters\Entity\PlanClusterInterface;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\BaseSubpageManager;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Plan cluster manager service class.
 */
class PlanClusterManager extends BaseSubpageManager {

  use LayoutEntityHelperTrait;
  use StringTranslationTrait;

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
   * @return \Drupal\ghi_plan_clusters\Entity\PlanClusterInterface
   *   The plan cluster subpage node.
   */
  public function loadClusterSubpageForBaseObject(BaseObjectInterface $base_object) {
    $nodes = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => PlanClusterInterface::BUNDLE,
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
   * @param \Drupal\ghi_sections\Entity\SectionNodeInterface $section
   *   The section that plan clusters belong to.
   *
   * @return \Drupal\ghi_plan_clusters\Entity\PlanCluster[]|null
   *   An array of entity objects indexed by their ids.
   */
  public function loadNodesForSection(SectionNodeInterface $section) {
    // Now find all governing entity base objects that reference the plan base
    // object.
    $governing_entities = $this->loadGoverningEntityBaseObjectsForSection($section);
    if (empty($governing_entities)) {
      return NULL;
    }
    $plan_clusters = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => PlanClusterInterface::BUNDLE,
      'field_entity_reference' => $section->id(),
      'field_base_object' => array_map(function (BaseObjectInterface $base_object) {
        return $base_object->id();
      }, $governing_entities),
    ]);
    return !empty($plan_clusters) ? $plan_clusters : NULL;
  }

  /**
   * Load all plan cluster specific logframe nodes for the given section.
   *
   * @param \Drupal\node\NodeInterface $section
   *   The section for which to load the nodes.
   *
   * @return \Drupal\ghi_subpages\Entity\LogframeSubpage[]
   *   An array of logframe node objects.
   */
  public function loadPlanClusterLogframeSubpageNodesForSection(NodeInterface $section) {
    $plan_clusters = $this->loadNodesForSection($section) ?? NULL;
    if (!$plan_clusters) {
      return [];
    }
    return $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => 'logframe',
      'field_entity_reference' => array_map(function (NodeInterface $plan_cluster) {
        return $plan_cluster->id();
      }, $plan_clusters),
    ]);
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
    if (!SubpageHelper::getSubpageManager()->isBaseTypeNode($node)) {
      return;
    }

    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->entityTypeManager->getStorage('node');
    $parent_node = $node_storage->load($node->id());
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
      $subpage = $node_storage->create([
        'type' => PlanClusterInterface::BUNDLE,
        'title' => $governing_entity->label(),
        'uid' => $parent_node->uid,
        'status' => NodeInterface::NOT_PUBLISHED,
        'field_base_object' => [
          'target_id' => $governing_entity->id(),
        ],
        'field_entity_reference' => [
          'target_id' => $parent_node->id(),
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
   * Assure the existence of logframe subpages for section cluster pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   * @param bool $rebuild
   *   Whether to rebuild existing logframe pages.
   */
  public function assureLogframeSubpagesForBaseNode(NodeInterface $node, $rebuild = FALSE) {
    if (!SubpageHelper::getSubpageManager()->isBaseTypeNode($node)) {
      return;
    }
    $cluster_subpages = $this->loadNodesForSection($node) ?? [];
    $node_storage = $this->entityTypeManager->getStorage('node');
    foreach ($cluster_subpages as $cluster_subpage) {
      $logframe = $cluster_subpage->getLogframeNode();
      if ($logframe && !$rebuild) {
        continue;
      }
      $status = SAVED_UPDATED;
      if (!$logframe) {
        /** @var \Drupal\ghi_subpages\Entity\LogframeSubpage $logframe */
        $logframe = $node_storage->create([
          'type' => 'logframe',
          'title' => $cluster_subpage->label() . ' logframe',
          'uid' => $cluster_subpage->uid,
          'status' => NodeInterface::NOT_PUBLISHED,
          'field_entity_reference' => [
            'target_id' => $cluster_subpage->id(),
          ],
        ]);
        $status = $logframe->save();
      }

      if ($logframe) {
        $logframe->createPageElements();
      }

      $t_args = [
        '@title' => $cluster_subpage->label(),
      ];
      if ($status == SAVED_NEW) {
        $status_message = $this->t('Created logframe subpage for @title', $t_args);
      }
      else {
        $status_message = $this->t('Rebuild logframe subpage for @title', $t_args);
      }
      $this->messenger->addStatus($status_message);
    }
  }

  /**
   * Delete all subpages for a base node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The base node.
   */
  public function deleteSubpagesForBaseNode(NodeInterface $node) {
    if (!SubpageHelper::getSubpageManager()->isBaseTypeNode($node)) {
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
    if ($node->bundle() != PlanClusterInterface::BUNDLE) {
      return NULL;
    }
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    if (!$base_object || !$base_object->hasField('field_plan') || $base_object->get('field_plan')->isEmpty()) {
      // We only support plan sections for now.
      return NULL;
    }

    $plan_object = $base_object->get('field_plan')->entity;
    return $this->sectionManager->loadSectionForBaseObject($plan_object);
  }

  /**
   * Alter the node edit forms for plan cluster nodes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function nodeEditFormAlter(&$form, FormStateInterface &$form_state) {
    /** @var \Drupal\node\NodeForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\ghi_plan_clusters\Entity\PlanCluster $entity */
    $entity = $form_object->buildEntity($form, $form_state);

    // Disable the title.
    $form['title']['#disabled'] = TRUE;
    $form['title']['widget'][0]['value']['#description'] = $this->t('The default page title cannot be changed because it is synced from the @entity_type_label data object. To use a non-default title for this page, use the "Title override" field below.', [
      '@entity_type_label' => strtolower($entity->getBaseObjectType()->label()),
    ]);

    // Always disable the base object field if the cluster has a base object set
    // already. There is no use case to change it. Display it for convenience.
    $object = $entity->getBaseObject();
    if ($object instanceof GoverningEntity) {
      $field_name = PlanClusterInterface::BASE_OBJECT_FIELD_NAME;
      $form[$field_name]['#disabled'] = !$this->currentUser->hasPermission('administer site configuration');
      $form[$field_name]['widget'][0]['target_id']['#description'] = $this->t('The base object cannot be changed after the initial creation of a @entity_type_label page. <a href="@base_object_edit_url" target="_blank">Edit @label</a>', [
        '@entity_type_label' => strtolower($entity->type->entity->label()),
        '@base_object_edit_url' => $object->toUrl('edit-form')->toString(),
        '@label' => $object->label(),
      ]);
    }

    // Always disable the section reference if the cluster has a reference set
    // already. There is no use case to change it. Display it for convenience.
    $section = $entity->getParentNode();
    if ($section instanceof SectionNodeInterface) {
      $field_name = PlanClusterInterface::SECTION_REFERENCE_FIELD_NAME;
      $form[$field_name]['#disabled'] = !$this->currentUser->hasPermission('administer site configuration');
      $form[$field_name]['widget'][0]['target_id']['#description'] = $this->t('The section cannot be changed after the initial creation of a @entity_type_label page. <a href="@section_url" target="_blank">View @label</a>', [
        '@entity_type_label' => strtolower($entity->type->entity->label()),
        '@section_url' => $section->toUrl()->toString(),
        '@label' => $section->label(),
      ]);
    }
  }

}
