<?php

namespace Drupal\ghi_plans\SyncElements;

use Drupal\node\Entity\Node;

/**
 * Methods for running the element sync in a batch.
 *
 * @see \Drupal\ghi_plans\SyncElements
 */
class SyncElementsBatch {

  /**
   * Processes the element sync batch.
   *
   * @param \Drupal\ghi_plans\SyncElements\SyncElementsManager $sync_manager
   *   The sync manager to use.
   * @param array $bundle
   *   The bundles to process.
   * @param array $ids
   *   Optional: The ids to process.
   * @param array $context
   *   The batch context.
   */
  public static function process(SyncElementsManager $sync_manager, array $bundle, array $ids, array &$context) {
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

    $sync_manager = $context['sandbox']['sync_manager'];
    $node = Node::load(array_shift($context['sandbox']['node_ids']));
    try {
      $messages = $sync_manager->syncNode($node);
      if (!empty($messages)) {
        $context['results']['processed']++;
      }
      else {
        $context['results']['skipped']++;
      }
    }
    catch (SyncElementsException $e) {
      $context['results']['errors'][] = t('@bundle @original_id (@node_id): @message', [
        '@bundle' => $node->type->entity->label(),
        '@original_id' => $node->field_original_id->value,
        '@node_id' => $node->id(),
        '@message' => $e->getMessage(),
      ]);
      $context['results']['skipped']++;
    }

    // Set progress.
    $context['finished'] = ($context['sandbox']['total'] - count($context['sandbox']['node_ids'])) / $context['sandbox']['total'];
  }

  /**
   * Finish batch.
   *
   * This function is a static function to avoid serializing the ConfigSync
   * object unnecessarily.
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
