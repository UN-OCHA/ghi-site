<?php

/**
 * @file
 * Contains deploy functions for the GHI Content module.
 */

use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\ContentBase;

/**
 * Populate the new orphaned field.
 */
function ghi_content_deploy_populate_orphaned_field(&$sandbox) {
  if (!isset($sandbox['nodes'])) {
    // Get existing content.
    $content_types = [
      ArticleManager::ARTICLE_BUNDLE,
      DocumentManager::DOCUMENT_BUNDLE,
    ];
    $nodes = \Drupal::entityQuery('node')->condition('type', $content_types, 'IN')->accessCheck(FALSE)->execute();
    $sandbox['nodes'] = $nodes;
  }

  $node_id = array_shift($sandbox['nodes']);
  $node = $node_id ? \Drupal::entityTypeManager()->getStorage('node')->load($node_id) : NULL;
  if ($node instanceof ContentBase) {
    $node->setOrphaned(FALSE);
    $node->setNewRevision(FALSE);
    $node->setSyncing(TRUE);
    $node->save();
  }
  $sandbox['#finished'] = 1 / (count($sandbox['nodes']) + 1);
}
