<?php

/**
 * @file
 * Contains deploy functions for the GHI Blocks module.
 */

use Drupal\Core\Utility\Error;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;

/**
 * Remove plan webcontent text blocks from revisions.
 */
function ghi_blocks_deploy_9014_remove_plan_webcontent_text_blocks(&$sandbox) {
  $count_revisions = 0;
  $result = \Drupal::database()->select('node_revision__layout_builder__layout')
    ->fields('node_revision__layout_builder__layout', ['revision_id'])
    ->condition('layout_builder__layout_section', '%plan_webcontent_text%', 'LIKE')
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
        if ($component->getPluginId() != 'plan_webcontent_text') {
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

/**
 * Queue active plan headline figures for source-aware metric migration.
 */
function ghi_blocks_deploy_9015_migrate_plan_headline_figures(&$sandbox) {
  $plugin_id = 'plan_headline_figures';
  $queue_id = 'ghi_blocks_migrate_plan_headline_figures';
  // The migration is best effort. Never keep the deployment open while
  // headline figure configurations are processed against remote data.
  $sandbox['#finished'] = 1;

  try {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = \Drupal::service('queue')->get($queue_id);
    // This migration intentionally targets current entity data only. Historical
    // node revisions keep the configuration that was active for that revision.
    \Drupal::service('ghi_blocks.node_queue')->queueNodesForPlugin($plugin_id, $queue_id);
    \Drupal::service('ghi_blocks.page_template_queue')->queuePageTemplatesForPlugin($plugin_id, $queue_id);
    return t('Queued @count active entities for plan headline figure migration. The migration will be processed by cron.', [
      '@count' => $queue->numberOfItems(),
    ]);
  }
  catch (\Throwable $exception) {
    // Updating these configurations is optional because their legacy item
    // types remain supported. Log the problem without interrupting deployment.
    try {
      Error::logException(
        \Drupal::logger('ghi_blocks'),
        $exception,
        'Plan headline figure migration could not be fully queued: @message',
      );
    }
    catch (\Throwable) {
      // Logging must not turn this best-effort migration into a deployment
      // failure either.
    }
    return t('Plan headline figure migration could not be fully queued. Deployment continued and existing configurations remain supported.');
  }
}
