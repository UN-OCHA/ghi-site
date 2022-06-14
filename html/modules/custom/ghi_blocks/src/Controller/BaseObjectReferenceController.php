<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Controller class for base object references in GHI blocks.
 */
class BaseObjectReferenceController extends ControllerBase implements ContainerInjectionInterface {

  use LayoutEntityHelperTrait;

  /**
   * Alter a node form to give feedback about data blocks to remove.
   *
   * This assumes that the node type in question uses a multi-value entity
   * reference field called 'field_base_objects' with the form widget from
   * wmbert.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @see ghi_blocks_form_node_form_alter()
   */
  public function nodeFormAlter(array &$form, FormStateInterface $form_state) {
    $node = $form_state->getFormObject()->getEntity();
    $entity_ids_current = [];
    if (array_key_exists('list', $form['field_base_objects']['widget'])) {
      $widget_list = &$form['field_base_objects']['widget']['list'];
      $entity_ids_current = array_map(function ($element_key) use ($widget_list) {
        return $widget_list[$element_key]['entity']['#value'];
      }, Element::children($widget_list) ?? []);
    }
    $entity_ids_original = array_map(function ($item) {
      return $item->id();
    }, $node->get('field_base_objects')->referencedEntities() ?? []);
    $removed_entity_ids = array_diff($entity_ids_original, $entity_ids_current);
    if (!empty($removed_entity_ids)) {
      $base_objects = $this->entityTypeManager()->getStorage('base_object')->loadMultiple($removed_entity_ids);
      $object_blocks = [];
      foreach ($base_objects as $base_object) {
        $components = $this->getComponentsByBaseObject($node, $base_object);
        if (empty($components)) {
          continue;
        }
        $blocks = [];
        foreach ($components as $component) {
          $plugin = $component->getPlugin();
          $blocks[] = $plugin->label() ?? $plugin->getPluginDefinition()['admin_label'];
        }
        if (!empty($blocks)) {
          $object_blocks[] = $this->t('@object: @blocks', [
            '@object' => $base_object->label(),
            '@blocks' => implode(', ', $blocks),
          ]);
        }
      }
      if (!empty($blocks)) {
        $this->messenger()->addWarning($this->t('The following data elements will be permanently removed from this page:<br />@blocks', [
          '@blocks' => Markup::create(ThemeHelper::render([
            '#theme' => 'item_list',
            '#items' => $object_blocks,
          ], FALSE)),
        ]));
        $form['field_base_objects']['widget']['warning'] = [
          '#type' => 'status_messages',
          '#weight' => -10,
        ];
      }
    }
  }

  /**
   * Extract base object components from an array of sections.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   An array of components if found.
   */
  public function extractBaseObjectComponentsFromSection(array $sections, BaseObjectInterface $base_object) {
    $components = [];
    foreach ($sections as $section) {
      $component = $section->getComponents();
      foreach ($component as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof GHIBlockBase) {
          continue;
        }
        $context_mapping = $plugin->getContextMapping();
        if (empty($context_mapping)) {
          continue;
        }
        foreach ($context_mapping as $context_key) {
          if (!strpos($context_key, '--')) {
            continue;
          }
          if ($base_object->getUniqueIdentifier() != $context_key) {
            continue;
          }
          $components[] = $component;
        }
      }
    }
    return $components;
  }

  /**
   * Find block components that depend on the given base object.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The base object.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   An array of components.
   */
  public function getComponentsByBaseObject(NodeInterface $node, BaseObjectInterface $base_object) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage || !$node->hasField('field_base_objects') || $node->get('field_base_objects')->isEmpty()) {
      return [];
    }
    $sections = $section_storage->getSections();
    $components = $this->extractBaseObjectComponentsFromSection($sections, $base_object);
    return $components;
  }

  /**
   * Extract base object components that are orphaned.
   *
   * This searches for components that reference a base object that is not part
   * of the given list of base objects.
   *
   * @param \Drupal\layout_builder\Section[] $sections
   *   An array of section objects.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface[] $base_objects
   *   The base object.
   *
   * @return \Drupal\layout_builder\SectionComponent[]
   *   An array of components if found.
   */
  public function extractOrphanedBaseObjectComponentsFromSections(array $sections, array $base_objects) {
    $components = [];
    // First collect the data object context keys to identify the components.
    $context_keys = array_map(function ($object) {
      return $object->getUniqueIdentifier();
    }, $base_objects);
    // Iterate over all components in all sections and see if there is a block
    // that references one of our custom base objects, which is not part of the
    // given base objects.
    foreach ($sections as $delta => $section) {
      $component = $section->getComponents();
      foreach ($component as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof GHIBlockBase) {
          continue;
        }
        $context_mapping = $plugin->getContextMapping();
        if (empty($context_mapping)) {
          continue;
        }
        foreach ($context_mapping as $context_name => $context_key) {
          if (!strpos($context_key, '--')) {
            continue;
          }
          if (in_array($context_key, $context_keys)) {
            continue;
          }
          [$bundle] = explode('--', $context_key);
          if ($bundle != $context_name) {
            // This looks fishy and we really don't want to wrongfully mark a
            // component as obsolete.
            continue;
          }
          $components[$delta][] = $component;
        }
      }
    }
    return $components;
  }

  /**
   * Find block components that are obsolete.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return array
   *   An array of component arrays, keyed by the delta of the section that
   *   they belong to.
   */
  public function getOrphanedBaseObjectComponents(NodeInterface $node) {
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage || !$node->hasField('field_base_objects')) {
      return [];
    }
    $sections = $section_storage->getSections();
    $base_objects = $node->get('field_base_objects')->referencedEntities();
    $components = $this->extractOrphanedBaseObjectComponentsFromSections($sections, $base_objects);
    return $components;
  }

  /**
   * Cleanup data blocks if references to data objects have been remved.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @see ghi_content_node_presave()
   */
  public function cleanupDataBlocks(NodeInterface $node) {
    $obsolete_components = $this->getOrphanedBaseObjectComponents($node);
    $section_storage = $this->getSectionStorageForEntity($node);
    if (!$section_storage || empty($obsolete_components)) {
      return;
    }
    $sections = $section_storage->getSections();
    foreach ($obsolete_components as $delta => $components) {
      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        $this->messenger()->addStatus($this->t('Removed block @block_name', [
          '@block_name' => $plugin->label() ?? $plugin->getPluginDefinition()['admin_label'],
        ]));
        $sections[$delta]->removeComponent($component->getUuid());
      }
    }
  }

}
