<?php

namespace Drupal\ghi_blocks\Services;

/**
 * Service class for configuration updates of plugins.
 */
class NodeQueue extends BaseQueue {

  /**
   * Queue nodes for updates to the plugin configuration.
   *
   * @param string $plugin_id
   *   The id of the plugin to update.
   * @param string $queue_id
   *   The queue id the node should be added to.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  public function queueNodesForPlugin($plugin_id, $queue_id) {
    $result = $this->database->select('node__layout_builder__layout')
      ->fields('node__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%' . $plugin_id . '%', 'LIKE')
      ->orderBy('entity_id')
      ->execute();

    $queue = $this->queueFactory->get($queue_id);
    foreach ($result->fetchAll() as $row) {
      $queue->createItem((object) [
        'entity_id' => $row->entity_id,
        'entity_type_id' => 'node',
        'plugin_id' => $plugin_id,
      ]);
    }
    return $queue;
  }

  /**
   * Queue node revisions for updates to the plugin configuration.
   *
   * @param string $plugin_id
   *   The id of the plugin to update.
   * @param string $queue_id
   *   The queue id the node revision should be added to.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue.
   */
  public function queueNodeRevisionsForPlugin($plugin_id, $queue_id) {
    $result = $this->database->select('node_revision__layout_builder__layout')
      ->fields('node_revision__layout_builder__layout', ['entity_id'])
      ->condition('layout_builder__layout_section', '%' . $plugin_id . '%', 'LIKE')
      ->orderBy('entity_id')
      ->distinct()
      ->execute();

    // This actually queues the node ids and not the revision ids. That is
    // intentional, assuming that the queue worker is based on processing
    // entities, but also checks if revisions are supported and then processes
    // these too.
    // See \Drupal\ghi_blocks\Plugin\QueueWorker\PluginConfigurationUpdate for
    // an example.
    $queue = $this->queueFactory->get($queue_id);
    foreach ($result->fetchAll() as $row) {
      $queue->createItem((object) [
        'entity_id' => $row->entity_id,
        'entity_type_id' => 'node',
        'plugin_id' => $plugin_id,
      ]);
    }
    return $queue;
  }

}
