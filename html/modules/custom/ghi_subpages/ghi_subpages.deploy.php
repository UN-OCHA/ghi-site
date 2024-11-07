<?php

/**
 * @file
 * Post update functions for GHI Subpages.
 */

use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Create new standard subpages.
 */
function ghi_subpages_deploy_create_standard_subpages(&$sandbox) {
  if (!isset($sandbox['sections'])) {
    // Get existing content of type "section".
    $sections = \Drupal::entityQuery('node')->condition('type', 'section')->accessCheck(FALSE)->execute();
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
  $node_ids = \Drupal::entityQuery('node')->condition('type', 'logframe')->accessCheck(FALSE)->execute();
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
    $sandbox['nodes'] = \Drupal::entityQuery('node')->condition('type', SubpageManager::SUPPORTED_SUBPAGE_TYPES, 'IN')->accessCheck(FALSE)->execute();
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

/**
 * Delete obsolete subpages for homepages.
 */
function ghi_subpages_deploy_delete_homepage_subpages(&$sandbox) {
  $homepages = \Drupal::entityQuery('node')->condition('type', 'homepage')->accessCheck(FALSE)->execute();
  $subpages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'field_entity_reference' => $homepages,
  ]);
  foreach ($subpages as $subpage) {
    $subpage->delete();
  }
}

/**
 * Update configuration for autobuild headline figures element on logframes.
 */
function ghi_subpages_deploy_fix_logframe_headline_figures(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    $result = \Drupal::database()->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('bundle', 'logframe')
      ->condition('layout_builder__layout_section', "%plan_headline_figures%", 'LIKE')
      ->orderBy('entity_id')
      ->execute();
    $sandbox['nodes'] = array_map(function ($row) {
      return $row->entity_id;
    }, $result->fetchAll());
    $sandbox['total'] = count($sandbox['nodes']);
    $sandbox['updated'] = 0;
  }
  for ($i = 0; $i < 25; $i++) {
    if (empty($sandbox['nodes'])) {
      continue;
    }
    $node_id = array_shift($sandbox['nodes']);
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($node_id);
    if (!$node instanceof LogframeSubpage) {
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
      if ($component->getPluginId() != 'plan_headline_figures') {
        continue;
      }
      $configuration = $component->get('configuration');
      if (empty($configuration['hpc']['key_figures']['items'])) {
        continue;
      }
      foreach ($configuration['hpc']['key_figures']['items'] as $index => &$item) {
        $item['id'] = $index + 1;
      }
      $changed = TRUE;
      $component->setConfiguration($configuration);
    }

    if ($changed) {
      $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
      $node->save();
      $sandbox['updated']++;
    }
  }

  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
  if ($sandbox['#finished'] === 1) {
    return t('Updated key figures configurations in @count_changed / @count_total logframe nodes', [
      '@count_changed' => $sandbox['updated'],
      '@count_total' => $sandbox['total'],
    ]);
  }
  else {
    return t('Processed @count_processed / @count_total logframe nodes', [
      '@count_processed' => $sandbox['total'] - count($sandbox['nodes']),
      '@count_total' => $sandbox['total'],
    ]);
  }
}
