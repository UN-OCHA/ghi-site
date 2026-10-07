<?php

namespace Drupal\ghi_blocks\Services;

use Drupal\Core\Queue\QueueInterface;

/**
 * Queues nodes containing a specific Layout Builder block plugin.
 */
class NodeQueue extends BaseQueue {

  /**
   * Queues current node revisions containing the plugin.
   */
  public function queueNodesForPlugin(string $plugin_id, string $queue_id): QueueInterface {
    return $this->queueEntitiesFromTables(['node__layout_builder__layout'], $plugin_id, $queue_id);
  }

  /**
   * Queues nodes with any revision containing the plugin.
   *
   * The queue contains node IDs because the generic workers process all
   * revisions for each queued node.
   */
  public function queueNodeRevisionsForPlugin(string $plugin_id, string $queue_id): QueueInterface {
    return $this->queueEntitiesFromTables(['node_revision__layout_builder__layout'], $plugin_id, $queue_id);
  }

  /**
   * Queues nodes whose current layout or any revision contains the plugin.
   */
  public function queueNodesAndRevisionsForPlugin(string $plugin_id, string $queue_id): QueueInterface {
    return $this->queueEntitiesFromTables([
      'node__layout_builder__layout',
      'node_revision__layout_builder__layout',
    ], $plugin_id, $queue_id);
  }

  /**
   * Queues unique entity IDs selected from Layout Builder field tables.
   */
  private function queueEntitiesFromTables(array $tables, string $plugin_id, string $queue_id): QueueInterface {
    $entity_ids = [];
    foreach ($tables as $table) {
      $result = $this->database->select($table)
        ->fields($table, ['entity_id'])
        ->condition('layout_builder__layout_section', '%' . $this->database->escapeLike($plugin_id) . '%', 'LIKE')
        ->orderBy('entity_id')
        ->distinct()
        ->execute();
      foreach ($result->fetchAll() as $row) {
        $entity_ids[$row->entity_id] = $row->entity_id;
      }
    }
    ksort($entity_ids);

    $queue = $this->queueFactory->get($queue_id);
    foreach ($entity_ids as $entity_id) {
      $queue->createItem((object) [
        'entity_id' => $entity_id,
        'entity_type_id' => 'node',
        'plugin_id' => $plugin_id,
      ]);
    }
    return $queue;
  }

}
