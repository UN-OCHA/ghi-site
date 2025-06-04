<?php

/**
 * @file
 * Contains deploy functions for the GHI Content module.
 */

use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\taxonomy\TermInterface;

/**
 * Populate the new orphaned field.
 */
function ghi_content_deploy_populate_orphaned_field(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    // Get existing content.
    $content_types = [
      ArticleManager::ARTICLE_BUNDLE,
      DocumentManager::DOCUMENT_BUNDLE,
    ];
    $nodes = \Drupal::entityQuery('node')->condition('type', $content_types, 'IN')->accessCheck(FALSE)->execute();
    $sandbox['nodes'] = $nodes;
  }

  $node_id = array_shift($sandbox['nodes']);
  $node = $node_id ? \Drupal::entityTypeManager()->getStorage('node')->load($node_id) : NULL;
  if ($node instanceof ContentBase) {
    $node->setOrphaned(FALSE);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
}

/**
 * Update configuration of related_articles plugins.
 */
function ghi_content_deploy_queue_related_articles_block_configuration_update(&$sandbox) {
  set_time_limit(0);
  $context = [
    'sandbox' => &$sandbox,
  ];
  if (!array_key_exists('queue_id', $sandbox)) {
    // Queue affected nodes for updating. This will process all nodes that are
    // using the plugin in it's current version.
    $plugin_id = 'related_articles';
    $queue_id = 'ghi_blocks_plugin_configuration_update';
    /** @var \Drupal\ghi_blocks\Helpers\NodeQueue $node_queue */
    $node_queue = \Drupal::service('ghi_blocks.node_queue');
    $queue = $node_queue->queueNodesForPlugin($plugin_id, $queue_id);
    $sandbox['#finished'] = 0;
    $sandbox['plugin_id'] = $plugin_id;
    $sandbox['queue_id'] = $queue_id;
    return (string) t('Enqueued @total nodes to update @plugin_id plugin configurations.', [
      '@total' => $queue->numberOfItems(),
      '@plugin_id' => $plugin_id,
    ]);
  }

  // Process the queued items now, using the batch processing of the queue_ui
  // module.
  /** @var \Drupal\queue_ui\QueueUIBatch $queue_ui_batch */
  $queue_ui_batch = \Drupal::service('queue_ui.batch');
  $queue_ui_batch->step($sandbox['queue_id'], $context);

  // Check if we are finished.
  $sandbox['#finished'] = $context['finished'];
  if ($sandbox['#finished'] == 1) {
    // Also queue the revisions for updating, but that can be handled later by
    // cron.
    /** @var \Drupal\ghi_blocks\Helpers\NodeQueue $node_queue */
    $node_queue = \Drupal::service('ghi_blocks.node_queue');
    $node_queue->queueNodeRevisionsForPlugin($sandbox['plugin_id'], $sandbox['queue_id']);
  }
  return $context['message'];
}

/**
 * Import the tag types from the remote source.
 */
function ghi_content_deploy_update_tag_type_data(&$sandbox) {
  if (!isset($sandbox['tag_ids'])) {
    // Get existing tags.
    $tags = \Drupal::entityQuery('taxonomy_term')->condition('vid', 'tags')->accessCheck(FALSE)->execute();
    $sandbox['tag_ids'] = $tags;
  }

  /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source */
  $remote_source = \Drupal::service('plugin.manager.remote_source')->createInstance('hpc_content_module');
  $tag_id = array_shift($sandbox['tag_ids']);
  $tag = $tag_id ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($tag_id) : NULL;
  if ($tag instanceof TermInterface && $remote_tag = $remote_source->getTag($tag->label())) {
    $tag->set('field_type', $remote_tag->getType());
    $tag->setNewRevision(FALSE);
    $tag->setSyncing(TRUE);
    $tag->save();
  }
  $sandbox['#finished'] = 1 / (count($sandbox['tag_ids']) + 1);
}

/**
 * Cleanup faulty migrate map entries.
 */
function ghi_content_deploy_cleanup_migrate_map_entries(&$sandbox) {
  $query = \Drupal::database()->delete('migrate_map_articles_hpc_content_module');
  $query->isNull('destid1');
  $query->execute();
}
