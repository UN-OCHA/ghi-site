<?php

/**
 * @file
 * Post update functions for GHI Subpages.
 */

use Drupal\ghi_subpages\SubpageManager;

/**
 * Create new standard subpages.
 */
function ghi_subpages_deploy_create_standard_subpages(&$sandbox) {
  if (!isset($sandbox['sections'])) {
    // Get existing content of type "section".
    $sections = \Drupal::entityQuery('node')->condition('type', 'section')->execute();
    $sandbox['sections'] = $sections;
  }

  $section_id = array_shift($sandbox['sections']);
  $section = $section_id ? \Drupal::entityTypeManager()->getStorage('node')->load($section_id) : NULL;
  if ($section) {
    $section->save();
  }
  $sandbox['#finished'] = 1 / (count($sandbox['sections']) + 1);
}

/**
 * Queue logframe pages for rebuilding.
 */
function ghi_subpages_deploy_queue_logframes(&$sandbox) {
  // Queue all logframes for rebuilding.
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $node_ids = \Drupal::entityQuery('node')->condition('type', 'logframe')->execute();
  foreach ($node_ids as $node_id) {
    \Drupal::queue('ghi_subpages_logframe_rebuild_queue')->createItem((object) [
      'entity_id' => $node_id,
      'entity_type_id' => 'node',
    ]);
  }
  return (string) t('Enqueued @total logframe nodes for rebuilding.', [
    '@total' => \Drupal::queue('ghi_subpages_logframe_rebuild_queue')->numberOfItems(),
  ]);
}

/**
 * Recreate the url aliases.
 */
function ghi_subpages_deploy_update_subpage_url_aliases(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    $sandbox['nodes'] = \Drupal::entityQuery('node')->condition('type', SubpageManager::SUPPORTED_SUBPAGE_TYPES, 'IN')->execute();
    drupal_flush_all_caches();
    \Drupal::service('pathauto.generator')->resetCaches();
  }

  $node_id = array_shift($sandbox['nodes']);
  $node = $node_id ? \Drupal::entityTypeManager()->getStorage('node')->load($node_id) : NULL;
  if ($node) {
    $node->save();
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
}
