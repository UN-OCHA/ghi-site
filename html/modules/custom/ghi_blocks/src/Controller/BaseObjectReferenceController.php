<?php

namespace Drupal\ghi_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
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
   * bert (better entity reference table).
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
    $base_object_storage = $this->entityTypeManager()->getStorage('base_object');
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
      $base_objects = $base_object_storage->loadMultiple($removed_entity_ids);
      $blocks = [];
      foreach ($base_objects as $base_object) {
        $components = $this->getComponentsByBaseObject($node, $base_object);
        if (empty($components)) {
          continue;
        }
        foreach ($components as $component) {
          $plugin = $component->getPlugin();
          if (!$plugin instanceof GHIBlockBase) {
            continue;
          }
          $blocks[$plugin->getUuid()] = $blocks[$plugin->getUuid()] ?? [
            'block_label' => $plugin->label() ?? $plugin->getPluginDefinition()['admin_label'],
            'base_object_labels' => [],
          ];
          $blocks[$plugin->getUuid()]['base_object_labels'][] = $base_object instanceof BaseObjectChildInterface ? $base_object->labelWithParent() : $base_object->label();
        }
      }
      if (!empty($blocks)) {
        $block_notifications = array_map(function ($block) {
          return [
            '#markup' => $block['block_label'],
            'children' => [
              '#theme' => 'item_list',
              '#items' => $block['base_object_labels'],
            ],
          ];
        }, $blocks);
        $this->messenger()->addWarning($this->t('The following data elements will be permanently removed from this page:<br />@blocks', [
          '@blocks' => Markup::create(ThemeHelper::render([
            '#theme' => 'item_list',
            '#items' => $block_notifications,
          ], FALSE)),
        ]));
        $form['field_base_objects']['widget']['warning'] = [
          '#type' => 'status_messages',
          '#weight' => -10,
        ];
      }
    }

    // Add the already added plan ids to the selection settings.
    if (!empty($form['field_base_objects']['widget']['add'])) {
      $base_objects = $base_object_storage->loadMultiple($entity_ids_current);
      $plan_objects = array_filter($base_objects, function (BaseObjectInterface $base_object) {
        return $base_object instanceof Plan;
      });
      $form['field_base_objects']['widget']['add']['entity']['#selection_settings']['selected_plans'] = array_keys($plan_objects);
    }

    $base_objects = !empty($entity_ids_current) ? $base_object_storage->loadMultiple($entity_ids_current) : [];
    if (!empty($base_objects)) {
      $governing_entity_objects = array_filter($base_objects, function (BaseObjectInterface $base_object) {
        return $base_object instanceof GoverningEntity;
      });
      $required_plan_entity_ids = array_map(function (GoverningEntity $base_object) {
        return $base_object->getPlan()->id();
      }, $governing_entity_objects);
      $widget_list = &$form['field_base_objects']['widget']['list'];
      foreach (Element::children($widget_list) as $element_key) {
        $element = &$widget_list[$element_key];
        $base_object = $base_object_storage->load($element['entity']['#value']);
        if ($base_object instanceof Plan && in_array($base_object->id(), $required_plan_entity_ids)) {
          $element['remove']['#disabled'] = 'disabled';
          $element['#attributes']['title'] = $this->t('This plan object cannot be removed until the dependant governing entity objects are removed.');
        }
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
      $section_components = $section->getComponents();
      uasort($section_components, function ($a, $b) {
        return $a->getWeight() - $b->getWeight();
      });
      foreach ($section_components as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof GHIBlockBase) {
          continue;
        }
        $context_mapping = $plugin->getContextMapping();
        if (empty($context_mapping)) {
          continue;
        }
        $selected_data_object_id = $plugin->getSelectedDataObjectId();
        if ($selected_data_object_id !== NULL) {
          // If a data object has been actively selected, use that as our only
          // requirement.
          if ($selected_data_object_id == $base_object->id()) {
            $components[] = $component;
          }
        }
        else {
          // Otherwhise go over the context mapping compare the context_key
          // with the base object's identifier.
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
    }
    uasort($components, function ($a, $b) {
      return $a->getWeight() - $b->getWeight();
    });
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
   * Cleanup data blocks if references to data objects have been removed.
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
