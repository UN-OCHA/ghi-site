<?php

/**
 * @file
 * Contains post update functions for the GHI Blocks module.
 */

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
