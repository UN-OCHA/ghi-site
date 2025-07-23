<?php

namespace Drupal\ghi_blocks\Services;

/**
 * Service class for configuration updates of plugins.
 */
class PageTemplateQueue extends BaseQueue {

  /**
   * Queue page templates for updates to the plugin configuration.
   *
   * @param string $plugin_id
   *   The id of the plugin to update.
   * @param string $queue_id
   *   The queue id the node should be added to.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  public function queuePageTemplatesForPlugin($plugin_id, $queue_id) {
    $result = $this->database->select('page_template__layout_builder__layout')
      ->fields('page_template__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%' . $plugin_id . '%', 'LIKE')
      ->orderBy('entity_id')
      ->execute();

    $queue = $this->queueFactory->get($queue_id);
    foreach ($result->fetchAll() as $row) {
      $queue->createItem((object) [
        'entity_id' => $row->entity_id,
        'entity_type_id' => 'page_template',
        'plugin_id' => $plugin_id,
      ]);
    }
    return $queue;
  }

}
