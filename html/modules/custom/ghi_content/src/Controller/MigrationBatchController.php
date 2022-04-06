<?php

namespace Drupal\ghi_content\Controller;

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
   * @param array|\DrushBatchContext $context
   *   The sandbox context.
   */
  public static function batchProcessCleanupArticles($migration_id, array $options, &$context) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    $migration = \Drupal::getContainer()->get('plugin.manager.migration')->createInstance($migration_id, $options['configuration'] ?? []);

    if (empty($context['sandbox']['nodes']) || empty($context['sandbox']['source_ids'])) {

      /** @var \Drupal\ghi_content\Plugin\migrate\source\RemoteSourceGraphQL $source */
      $source = $migration->getSourcePlugin();
      $source_iterator = $source->initializeIterator();
      $source_tags = $source->getSourceTags();

      /** @var \Drupal\ghi_content\ContentManager\ArticleManager $article_manager */
      $article_manager = \Drupal::service('ghi_content.manager.article');
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple(array_keys($source_tags));
      $existing_tagged_nodes = !empty($source_tags) ? $article_manager->loadNodesForTags($terms, NULL, 'AND', NULL, FALSE) : NULL;

      $source_keys = $source->getIds();
      $source_id_values = [];
      foreach ($source_iterator as $item) {
        $source_id_values[] = array_intersect_key($item, $source_keys);
      }

      $context['finished'] = 0;
      $context['sandbox'] = [];
      $context['sandbox']['total'] = count($existing_tagged_nodes);
      $context['sandbox']['nodes'] = $existing_tagged_nodes;
      $context['sandbox']['source_ids'] = $source_id_values;
      $context['sandbox']['updated'] = 0;
      $context['results'][$migration->id()] = [];
    }

    $node = array_shift($context['sandbox']['nodes']);
    if ($node instanceof NodeInterface) {
      $source_id = $migration->getIdMap()->lookupSourceId(['nid' => $node->id()]);
      if (!in_array($source_id, $context['sandbox']['source_ids']) && $node->isPublished()) {
        $node->setUnpublished();
        $node->save();
        $context['sandbox']['updated']++;
      }
      if (in_array($source_id, $context['sandbox']['source_ids']) && !$node->isPublished()) {
        $node->setPublished();
        $node->save();
        $context['sandbox']['updated']++;
      }
    }

    $context['finished'] = ((float) ($context['sandbox']['total'] - count($context['sandbox']['nodes'])) / (float) $context['sandbox']['total']);
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
        $singular_message = "Updated 1 previously imported article - done with post-processing of '@name'";
        $plural_message = "Updated @updated previously imported articles - done with post-processing of '@name'";
        \Drupal::messenger()->addStatus(\Drupal::translation()->formatPlural($result['@updated'],
          $singular_message,
          $plural_message,
          $result));
      }
    }
  }

}
