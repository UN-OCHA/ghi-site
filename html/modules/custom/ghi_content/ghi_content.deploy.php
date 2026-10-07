<?php

/**
 * @file
 * Contains deploy functions for the GHI Content module.
 */

/**
 * Update legacy Related Articles configuration in every layout revision.
 */
function ghi_content_deploy_update_legacy_related_articles_configuration(&$sandbox): string {
  $plugin_id = 'related_articles';
  $queue_id = 'ghi_blocks_plugin_configuration_update';

  if (empty($sandbox['configuration_update_queued'])) {
    /** @var \Drupal\ghi_blocks\Services\NodeQueue $node_queue */
    $node_queue = \Drupal::service('ghi_blocks.node_queue');
    $node_queue->queueNodesAndRevisionsForPlugin($plugin_id, $queue_id);

    /** @var \Drupal\ghi_blocks\Services\PageTemplateQueue $page_template_queue */
    $page_template_queue = \Drupal::service('ghi_blocks.page_template_queue');
    $queue = $page_template_queue->queuePageTemplatesForPlugin($plugin_id, $queue_id);

    $sandbox['configuration_update_queued'] = TRUE;
    $sandbox['#finished'] = 0;
    return (string) t('Queued @total entities to update @plugin_id plugin configuration.', [
      '@total' => $queue->numberOfItems(),
      '@plugin_id' => $plugin_id,
    ]);
  }

  $context = ['sandbox' => &$sandbox];
  \Drupal::service('queue_ui.batch')->step($queue_id, $context);
  $sandbox['#finished'] = $context['finished'];
  return (string) ($context['message'] ?? t('Updated all @plugin_id plugin configuration.', [
    '@plugin_id' => $plugin_id,
  ]));
}
