<?php

namespace Drupal\ghi_element_sync;

use Drupal\node\Entity\Node;

/**
 * Methods for running the element sync in a batch.
 *
 * @see \Drupal\ghi_element_sync
 */
class SyncBatch {

  /**
   * Processes the element sync batch.
   *
   * @param \Drupal\ghi_element_sync\SyncManager $sync_manager
   *   The sync manager to use.
   * @param array $bundle
   *   The bundles to process.
   * @param array $ids
   *   Optional: The ids to process.
   * @param bool $sync_elements
   *   Whether elements should be synced.
   * @param bool $sync_metadata
   *   Whether metadata should be synced too.
   * @param bool $revisions
   *   Whether new revisions should be created.
   * @param bool $cleanup
   *   Whether existing elements should be cleaned up first.
   * @param array|\DrushBatchContext $context
   *   An associative array or DrushBatchContext.
   */
  public static function process(SyncManager $sync_manager, array $bundle, array $ids, $sync_elements, $sync_metadata, $revisions, $cleanup, &$context) {
    if (!isset($context['sandbox']['sync_manager'])) {
      $context['sandbox']['sync_manager'] = $sync_manager;

      // The basic query to retrieve node ids.
      $query = \Drupal::entityQuery('node')
        ->condition('type', $bundle, 'IN');

      // Optionally restricted by specific ids.
      if (!empty($ids)) {
        $query->condition('id', $ids, 'IN');
      }

      $result = $query->execute();
      $context['sandbox']['node_ids'] = array_values($result);

      $context['sandbox']['total'] = count($context['sandbox']['node_ids']);
      $context['results']['processed'] = 0;
      $context['results']['skipped'] = 0;
      $context['results']['total'] = $context['sandbox']['total'];
      $context['results']['errors'] = [];
    }

    /** @var \Drupal\ghi_element_sync\SyncManager $sync_manager */
    $sync_manager = $context['sandbox']['sync_manager'];
    $node = Node::load(array_shift($context['sandbox']['node_ids']));

    $messenger = \Drupal::messenger();

    try {
      if ($sync_manager->syncNode($node, NULL, $messenger, $sync_elements, $sync_metadata, $revisions, $cleanup)) {
        $context['results']['processed']++;
      }
      else {
        $context['results']['skipped']++;
      }
    }
    catch (SyncException $e) {
      $context['results']['errors'][] = t('@bundle @original_id (@node_id): @message', [
        '@bundle' => $node->type->entity->label(),
        '@original_id' => $node->field_original_id->value,
        '@node_id' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      $context['results']['skipped']++;
    }

    $messenger->deleteAll();

    // Set progress.
    $context['finished'] = ($context['sandbox']['total'] - count($context['sandbox']['node_ids'])) / $context['sandbox']['total'];
  }

  /**
   * Finish batch.
   *
   * @param bool $success
   *   Indicate that the batch API tasks were all completed successfully.
   * @param array $results
   *   An array of all the results that were updated in update_do_one().
   * @param array $operations
   *   A list of the operations that had not been completed by the batch API.
   */
  public static function finish($success, array $results, array $operations) {
    $messenger = \Drupal::messenger();
    if ($success) {
      if (!empty($results['errors'])) {
        foreach ($results['errors'] as $error) {
          $messenger->addError($error);
        }
        $messenger->addWarning(t('The element sync had errors.'));
      }
      $messenger->addStatus(t('Successfully processed @processed nodes, skipped @skipped of a total of @total nodes.', [
        '@processed' => $results['processed'],
        '@skipped' => $results['skipped'],
        '@total' => $results['total'],
      ]));
    }
    else {
      // An error occurred.
      $message = t('An error occurred. Please check the logs.');
      $messenger->addError($message);
    }
  }

}
