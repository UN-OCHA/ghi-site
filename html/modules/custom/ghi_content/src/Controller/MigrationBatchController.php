<?php

namespace Drupal\ghi_content\Controller;

use Drupal\ghi_content\ContentManager\BaseContentManager;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\node\NodeInterface;

/**
 * Controller class for migration batches.
 *
 * This is used to assure correct status values for node articles.
 *
 * @see ghi_content_batch_alter().
 */
class MigrationBatchController {

  /**
   * Batch 'operation' callback.
   *
   * @param string $migration_id
   *   The migration id.
   * @param array $options
   *   The batch executable options.
   * @param \Drupal\ghi_content\ContentManager\BaseContentManager $content_manager
   *   The content manager class.
   * @param array|\DrushBatchContext $context
   *   The sandbox context.
   */
  public static function batchProcessCleanup($migration_id, array $options, BaseContentManager $content_manager, &$context) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::getContainer()->get('plugin.manager.migration')->createInstance($migration_id, $options['configuration'] ?? []);

    if (empty($context['sandbox']['nodes']) || empty($context['sandbox']['source_ids'])) {
      /** @var \Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL $source */
      $source = $migration->getSourcePlugin();
      $source_iterator = $source->initializeIterator();
      $source_tags = $source->getSourceTags();

      if (!empty($source_tags)) {
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple(array_keys($source_tags));
        $nodes = $content_manager->loadNodesForTags($terms, NULL, 'AND', NULL, FALSE);
      }
      else {
        $nodes = $content_manager->loadAllNodes(FALSE);
      }

      $source_keys = $source->getIds();
      $source_id_values = [];
      foreach ($source_iterator as $item) {
        $source_id_values[] = array_intersect_key($item, $source_keys);
      }

      $context['finished'] = 0;
      $context['sandbox'] = [];
      $context['sandbox']['total'] = count($nodes);
      $context['sandbox']['nodes'] = $nodes;
      $context['sandbox']['source_ids'] = $source_id_values;
      $context['sandbox']['updated'] = 0;
      $context['results'][$migration->id()] = [];
    }

    if (!empty($context['sandbox']['nodes'])) {
      $node = array_shift($context['sandbox']['nodes']);
      // Let us only do the following when the full imports are run.
      if ($node instanceof NodeInterface && empty($source_tags)) {
        $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
        $needs_saving = FALSE;
        if (!in_array($source_id, $context['sandbox']['source_ids']) && $node->isPublished()) {
          // Disappeared nodes should be unpublished.
          $node->setUnpublished();
          $needs_saving = TRUE;
        }
        if (in_array($source_id, $context['sandbox']['source_ids']) && !$node->isPublished()) {
          // New nodes should be published.
          // @todo Prevent publishing nodes that have been manually unpublished
          // in HA.
          $node->setPublished();
          $needs_saving = TRUE;
        }
        $orphaned = !in_array($source_id, $context['sandbox']['source_ids']);
        if (empty($source_tags) && $node instanceof ContentBase && $node->isOrphaned() != $orphaned) {
          $node->setOrphaned($orphaned);
          $node->setNewRevision(FALSE);
          $node->setSyncing(TRUE);
          $needs_saving = TRUE;
        }
        if ($needs_saving) {
          $node->save();
          $context['sandbox']['updated']++;
        }
      }
      $context['finished'] = ((float) ($context['sandbox']['total'] - count($context['sandbox']['nodes'])) / (float) $context['sandbox']['total']);
    }
    else {
      $context['finished'] = 1;
    }

    $context['message'] = t('Post-processing %migration (@percent%).', [
      '%migration' => $migration->label(),
      '@percent' => (int) ($context['finished'] * 100),
    ]);

    if ($context['finished']) {
      $context['results'][$migration->id()] = [
        '@updated' => $context['sandbox']['updated'],
        '@name' => $migration->id(),
      ];
    }

  }

  /**
   * Finished callback for import batches.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished($success, array $results, array $operations) {
    if ($success) {
      foreach ($results as $result) {
        if (empty($result['@updated'])) {
          // Only add a message if we actually changes something.
          continue;
        }
        $singular_message = "Updated 1 previously imported content item - done with post-processing of '@name'";
        $plural_message = "Updated @updated previously imported content items - done with post-processing of '@name'";
        \Drupal::messenger()->addStatus(\Drupal::translation()->formatPlural($result['@updated'],
          $singular_message,
          $plural_message,
          $result));
      }
    }
  }

}
