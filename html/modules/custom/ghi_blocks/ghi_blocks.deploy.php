<?php

/**
 * @file
 * Contains deploy functions for the GHI Blocks module.
 */

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;

/**
 * Remove sections without components if multiple sections exists for a node.
 */
function ghi_blocks_deploy_9001_remove_empty_layout_builder_sections(&$sandbox) {
  $count_nodes = 0;
  $count_sections = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('delta', 0, '>')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections) || count($sections) == 1) {
      continue;
    }
    $changed = FALSE;
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $section = &$sections[$delta]['section'];
      $components = $section->getComponents();
      if (empty($components)) {
        unset($sections[$delta]);
        $count_sections++;
        $count_nodes++;
        $changed = TRUE;
      }
    }
    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->setSyncing(TRUE);
      $node->save();
    }

  }
  return t('Removed @count_sections sections from @count_nodes nodes', [
    '@count_sections' => $count_sections,
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Merge multiple layout builder sections into one.
 */
function ghi_blocks_deploy_9002_merge_layout_builder_sections(&$sandbox) {
  $count_nodes = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('delta', 0, '>')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections) || count($sections) == 1) {
      continue;
    }
    $merged_components = [];
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      uasort($components, function (SectionComponent $a, SectionComponent $b) {
        return $a->getWeight() <=> $b->getWeight();
      });
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        $sections[$delta]['section']->removeComponent($component->getUuid());
        $component->setWeight(count($merged_components));
        $merged_components[$component->getUuid()] = $component;
      }
    }
    foreach (array_keys($sections) as $delta) {
      if ($delta == 0) {
        continue;
      }
      unset($sections[$delta]);
    }
    uasort($merged_components, function (SectionComponent $a, SectionComponent $b) {
      return $a->getWeight() <=> $b->getWeight();
    });
    foreach ($merged_components as $component) {
      $sections[0]['section']->appendComponent($component);
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setSyncing(TRUE);
    $node->save();
    $count_nodes++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Enqueue nodes for the replacement of the plan_entity_types plugin.
 */
function ghi_blocks_deploy_9003_queue_nodes_for_plan_entity_type_replacement(&$sandbox) {
  $plugin_id = 'plan_entity_types';
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
  foreach ($nodes as $node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    \Drupal::queue('ghi_blocks_replace_deprecated_blocks_queue')->createItem((object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
      'plugin_id' => $plugin_id,
    ]);
  }
  return (string) t('Enqueued @total nodes for replacing of deprecated plan element @plugin_id.', [
    '@total' => \Drupal::queue('ghi_blocks_replace_deprecated_blocks_queue')->numberOfItems(),
    '@plugin_id' => $plugin_id,
  ]);
}

/**
 * Remove obsolete field from layout sections to prevent warnings.
 */
function ghi_blocks_deploy_9004_remove_field_block(&$sandbox) {
  $count_nodes = 0;
  $result = \Drupal::database()->select('node__layout_builder__layout')
    ->fields('node__layout_builder__layout', ['entity_id'])
    ->condition('layout_builder__layout_section', '%field_chapter%', 'LIKE')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($row->entity_id);
    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        if ($component->getPluginId() != 'field_block:node:article:field_chapter') {
          continue;
        }
        $sections[$delta]['section']->removeComponent($component->getUuid());
      }
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $count_nodes++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_nodes,
  ]);
}

/**
 * Remove obsolete field from layout sections to prevent warnings (revisions).
 */
function ghi_blocks_deploy_9005_remove_field_block_revisions(&$sandbox) {
  $count_revisions = 0;
  $result = \Drupal::database()->select('node_revision__layout_builder__layout')
    ->fields('node_revision__layout_builder__layout', ['revision_id'])
    ->condition('layout_builder__layout_section', '%field_chapter%', 'LIKE')
    ->orderBy('entity_id')
    ->execute();

  foreach ($result as $row) {
    /** @var \Drupal\node\NodeInterface $revision */
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($row->revision_id);
    if (!$revision || !$revision->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $revision->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        if ($component->getPluginId() != 'field_block:node:article:field_chapter') {
          continue;
        }
        $sections[$delta]['section']->removeComponent($component->getUuid());
      }
    }

    $revision->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $revision->setNewRevision(FALSE);
    $revision->setSyncing(TRUE);
    $revision->save();
    $count_revisions++;
  }
  return t('Updated @count_nodes nodes', [
    '@count_nodes' => $count_revisions,
  ]);
}
