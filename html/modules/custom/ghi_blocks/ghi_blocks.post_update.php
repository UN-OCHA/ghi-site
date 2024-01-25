<?php

/**
 * @file
 * Contains post update functions for the GHI Blocks module.
 */

use Drupal\ghi_blocks\Helpers\UrlHelper;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Update layout builder lock settings for all nodes.
 */
function ghi_blocks_post_update_9000(&$sandbox) {
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
  foreach ($nodes as $node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    \Drupal::queue('ghi_blocks_section_lock_update_queue')->createItem((object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
    ]);
  }
  return (string) t('Enqueued @total nodes for section lock update.', [
    '@total' => \Drupal::queue('ghi_blocks_section_lock_update_queue')->numberOfItems(),
  ]);
}

/**
 * Update monitoring periods for data points to latest for all nodes.
 */
function ghi_blocks_post_update_9001($sandbox) {
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple();
  foreach ($nodes as $node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    \Drupal::queue('ghi_blocks_update_monitoring_period_queue')->createItem((object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
    ]);
  }
  return (string) t('Enqueued @total nodes for monitoring period update.', [
    '@total' => \Drupal::queue('ghi_blocks_update_monitoring_period_queue')->numberOfItems(),
  ]);
}

/**
 * Update layout builder lock settings for article pages.
 */
function ghi_blocks_post_update_9002(&$sandbox) {
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'article',
  ]);
  foreach ($nodes as $node) {
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    \Drupal::queue('ghi_blocks_section_lock_update_queue')->createItem((object) [
      'entity_id' => $node->id(),
      'entity_type_id' => $node->getEntityTypeId(),
    ]);
  }
  return (string) t('Enqueued @total nodes for section lock update.', [
    '@total' => \Drupal::queue('ghi_blocks_section_lock_update_queue')->numberOfItems(),
  ]);
}

/**
 * Update link configuration for plan headline figures element.
 */
function ghi_blocks_post_update_9003(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    $result = \Drupal::database()->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->orderBy('entity_id')
      ->execute();
    $sandbox['nodes'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['nodes']);
    $sandbox['updated'] = 0;
  }
  /** @var \Drupal\node\NodeInterface[] $nodes */
  for ($i = 0; $i < 10; $i++) {
    if (empty($sandbox['nodes'])) {
      continue;
    }
    $node_id = array_shift($sandbox['nodes']);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if (!$node) {
      continue;
    }

    $changed = FALSE;
    if (!$node->hasField(OverridesSectionStorage::FIELD_NAME)) {
      continue;
    }
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    if (empty($sections)) {
      continue;
    }
    /** @var \Drupal\layout_builder\Section $section */
    $section = &$sections[0]['section'];
    $components = $section->getComponents();
    if (empty($components)) {
      continue;
    }
    foreach ($components as $component) {
      if ($component->getPluginId() !== 'plan_headline_figures') {
        continue;
      }
      $configuration = $component->get('configuration');
      if (empty($configuration['hpc']['key_figures']['items'])) {
        continue;
      }
      foreach ($configuration['hpc']['key_figures']['items'] as &$item) {
        if ($item['item_type'] != 'item_group') {
          continue;
        }
        if (!empty($item['config']['add_link'])) {
          $item['config']['link'] = [
            'add_link' => TRUE,
            'link' => [
              'label' => $item['config']['link']['label'],
              'url' => UrlHelper::transformUrlToEntityUri($item['config']['link']['url'], 'https://humanitarianaction.info'),
            ],
          ];
        }
        unset($item['config']['add_link']);
        $changed = TRUE;
      }
      $component->setConfiguration($configuration);
    }
    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->setSyncing(TRUE);
      $node->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated item group configuration for @count_changed / @count_total nodes', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
}
