<?php

/**
 * @file
 * Contains post update hooks for the GHI Plan Element Sync module.
 */

use Drupal\ghi_element_sync\SyncException;
use Drupal\node\Entity\Node;

/**
 * General purpose function to update all page elements.
 */
function ghi_element_sync_update_page_elements(&$sandbox) {
  $run = &drupal_static(__FUNCTION__, FALSE);
  if ($run) {
    return t('skipping');
  }

  if (!isset($sandbox['sync_manager'])) {
    $sandbox['sync_manager'] = \Drupal::service('ghi_element_sync.sync_elements');

    // The basic query to retrieve node ids.
    $query = \Drupal::entityQuery('node')
      ->condition('type', ['section', 'plan_cluster'], 'IN');

    $result = $query->execute();
    $sandbox['node_ids'] = array_values($result);

    $sandbox['total'] = count($sandbox['node_ids']);
    $sandbox['results']['processed'] = 0;
    $sandbox['results']['skipped'] = 0;
    $sandbox['results']['total'] = $sandbox['total'];
  }

  /** @var \Drupal\ghi_element_sync\SyncManager $sync_manager */
  $sync_manager = $sandbox['sync_manager'];
  $node = Node::load(array_shift($sandbox['node_ids']));

  $messenger = \Drupal::messenger();

  try {
    if ($sync_manager->syncNode($node, NULL, $messenger, TRUE, TRUE, TRUE, TRUE)) {
      $sandbox['results']['processed']++;
    }
    else {
      $sandbox['results']['skipped']++;
    }
    $messenger->deleteAll();
  }
  catch (SyncException $e) {
    $sandbox['results']['skipped']++;
  }

  $sandbox['#finished'] = ($sandbox['total'] - count($sandbox['node_ids'])) / $sandbox['total'];
  if ($sandbox['#finished'] === 1) {
    $run = TRUE;
    return t('Processed @processed nodes, skipped @skipped of a total of @total nodes.', [
      '@processed' => $sandbox['results']['processed'],
      '@skipped' => $sandbox['results']['skipped'],
      '@total' => $sandbox['results']['total'],
    ]);
  }
}

/**
 * Update all pages after data structure changes to data points.
 */
function ghi_element_sync_post_update_sync_elements_0001(&$sandbox) {
  return ghi_element_sync_update_page_elements($sandbox);
}

/**
 * Update all pages after config map update for plan headline figures.
 */
function ghi_element_sync_post_update_sync_elements_0002(&$sandbox) {
  return ghi_element_sync_update_page_elements($sandbox);
}
