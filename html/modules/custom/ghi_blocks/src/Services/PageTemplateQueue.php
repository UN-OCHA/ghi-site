<?php

namespace Drupal\ghi_blocks\Services;

use Drupal\Core\Queue\QueueInterface;

/**
 * Queues page templates containing a specific Layout Builder block plugin.
 */
class PageTemplateQueue extends BaseQueue {

  /**
   * Queues page templates containing the plugin.
   */
  public function queuePageTemplatesForPlugin(string $plugin_id, string $queue_id): QueueInterface {
    $queue = $this->queueFactory->get($queue_id);
    $table = 'page_template__layout_builder__layout';
    if (!$this->database->schema()->tableExists($table)) {
      return $queue;
    }
    $result = $this->database->select($table)
      ->fields($table, ['entity_id'])
      ->condition('layout_builder__layout_section', '%' . $this->database->escapeLike($plugin_id) . '%', 'LIKE')
      ->orderBy('entity_id')
      ->distinct()
      ->execute();

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
