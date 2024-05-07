<?php

/**
 * @file
 * Contains deploy functions for the GHI Blocks module.
 */

use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityTypes;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
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
 * Replace instances of the plan_entity_types plugin.
 */
function ghi_blocks_deploy_9003_retire_plan_entity_types(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    $result = \Drupal::database()->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%plan_entity_types%', 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['nodes'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['nodes']);
    $sandbox['updated'] = 0;
  }

  /** @var \Drupal\Component\UuidInterface $uuid_generator */
  $uuid_generator = \Drupal::service('uuid');

  /** @var \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager */
  $block_plugin_manager = \Drupal::service('plugin.manager.block');
  $entity_logframe_plugin_definition = $block_plugin_manager->getDefinition('plan_entity_logframe', FALSE);

  for ($i = 0; $i < 10; $i++) {
    if (empty($sandbox['nodes'])) {
      continue;
    }
    $node_id = array_shift($sandbox['nodes']);

    /** @var \Drupal\node\NodeInterface $node */
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if (!$node) {
      continue;
    }

    if (!$node || !$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }

    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof PlanEntityTypes) {
          continue;
        }
        $config = array_filter([
          'id' => $entity_logframe_plugin_definition['id'],
          'provider' => $entity_logframe_plugin_definition['provider'],
          'context_mapping' => $plugin->getContextMapping(),
          'hpc' => [
            'entities' => $plugin->getBlockConfig(),
            'tables' => [
              'attachment_tables' => [],
            ],
          ],

        ]);
        // Create the new component.
        $entity_logframe_component = new SectionComponent($uuid_generator->generate(), $component->getRegion(), $config);
        $entity_logframe_component->setWeight($component->getWeight());

        // And put it into the same position as the component it replaces.
        $_section = $sections[$delta]['section']->toArray();
        array_splice($_section['components'], array_search($component->getUuid(), array_keys($_section['components'])), 1, [$entity_logframe_component->getUuid() => $entity_logframe_component->toArray()]);
        $sections[$delta]['section'] = Section::fromArray($_section);
      }
    }

    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
    $sandbox['updated']++;
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Replaced plan_entity_types block with plan_entity_logframe block in @count_changed nodes', [
      '@count_changed' => $sandbox['updated'],
    ]);
  }
}

/**
 * Replace instances of the plan_entity_types plugin.
 */
function ghi_blocks_deploy_9004_retire_plan_entity_types_revisions(&$sandbox) {
  if (!isset($sandbox['node_revisions'])) {
    $result = \Drupal::database()->select('node_revision__layout_builder__layout')
      ->fields('node_revision__layout_builder__layout', ['revision_id'])
      ->condition('layout_builder__layout_section', '%plan_entity_types%', 'LIKE')
      ->orderBy('revision_id')
      ->execute();
    $sandbox['node_revisions'] = array_map(function ($row) {
      return $row->revision_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['node_revisions']);
    $sandbox['updated'] = 0;
  }

  /** @var \Drupal\Component\UuidInterface $uuid_generator */
  $uuid_generator = \Drupal::service('uuid');

  /** @var \Drupal\Core\Block\BlockManagerInterface $block_plugin_manager */
  $block_plugin_manager = \Drupal::service('plugin.manager.block');
  $entity_logframe_plugin_definition = $block_plugin_manager->getDefinition('plan_entity_logframe', FALSE);

  for ($i = 0; $i < 10; $i++) {
    if (empty($sandbox['node_revisions'])) {
      continue;
    }
    $revision_id = array_shift($sandbox['node_revisions']);

    /** @var \Drupal\node\NodeInterface $revision */
    $revision = \Drupal::entityTypeManager()->getStorage('node')->loadRevision($revision_id);
    if (!$revision) {
      continue;
    }

    if (!$revision || !$revision->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $revision->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }

    foreach (array_keys($sections) as $delta) {
      /** @var \Drupal\layout_builder\Section $section */
      $components = $sections[$delta]['section']->getComponents();
      /** @var \Drupal\layout_builder\SectionComponent[] $components */
      foreach ($components as $component) {
        $plugin = $component->getPlugin();
        if (!$plugin instanceof PlanEntityTypes) {
          continue;
        }
        $config = array_filter([
          'id' => $entity_logframe_plugin_definition['id'],
          'provider' => $entity_logframe_plugin_definition['provider'],
          'context_mapping' => $plugin->getContextMapping(),
          'hpc' => [
            'entities' => $plugin->getBlockConfig(),
            'tables' => [
              'attachment_tables' => [],
            ],
          ],

        ]);
        // Create the new component.
        $entity_logframe_component = new SectionComponent($uuid_generator->generate(), $component->getRegion(), $config);
        $entity_logframe_component->setWeight($component->getWeight());

        // And put it into the same position as the component it replaces.
        $_section = $sections[$delta]['section']->toArray();
        array_splice($_section['components'], array_search($component->getUuid(), array_keys($_section['components'])), 1, [$entity_logframe_component->getUuid() => $entity_logframe_component->toArray()]);
        $sections[$delta]['section'] = Section::fromArray($_section);
      }
    }

    $revision->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $revision->setNewRevision(FALSE);
    $revision->setSyncing(TRUE);
    $revision->save();
    $sandbox['updated']++;
  }

  $sandbox['#finished'] = 1 / (count($sandbox['node_revisions']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Replaced plan_entity_types block with plan_entity_logframe block in @count_changed node revisions', [
      '@count_changed' => $sandbox['updated'],
    ]);
  }
}
