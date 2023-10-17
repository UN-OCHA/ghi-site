<?php

namespace Drupal\ghi_subpages;

use Drupal\node\Entity\Node;

/**
 * Rebuild logframe batch in a batch.
 *
 * @see \Drupal\ghi_subpages
 */
class LogframeRebuildBatch {

  /**
   * Processes the element sync batch.
   *
   * @param \Drupal\ghi_subpages\LogframeManager $logframe_manager
   *   The sync manager to use.
   * @param array|\DrushBatchContext $context
   *   An associative array or DrushBatchContext.
   */
  public static function process(LogframeManager $logframe_manager, &$context) {
    if (!isset($context['sandbox']['logframe_manager'])) {
      $context['sandbox']['logframe_manager'] = $logframe_manager;

      // The basic query to retrieve node ids.
      $query = \Drupal::entityQuery('node')
        ->condition('type', 'logframe');

      $result = $query->accessCheck(FALSE)->execute();
      $context['sandbox']['node_ids'] = array_values($result);

      $context['sandbox']['total'] = count($context['sandbox']['node_ids']);
      $context['results']['processed'] = 0;
      $context['results']['total'] = $context['sandbox']['total'];
    }

    /** @var \Drupal\ghi_subpages\LogframeManager $logframe_manager */
    $logframe_manager = $context['sandbox']['logframe_manager'];
    $node = Node::load(array_shift($context['sandbox']['node_ids']));

    $section_storage = $logframe_manager->setupLogframePage($node);
    if ($section_storage) {
      $section_storage->save();
    }
    $context['results']['processed']++;

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
      $messenger->addStatus(t('Successfully rebuild @processed logframe pages.', [
        '@processed' => $results['processed'],
      ]));
    }
    else {
      // An error occurred.
      $message = t('An error occurred. Please check the logs.');
      $messenger->addError($message);
    }
  }

}
