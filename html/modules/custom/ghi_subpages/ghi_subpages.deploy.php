<?php

/**
 * @file
 * Deploy functions for the GHI Subpages module.
 */

/**
 * Schedule Solr reindexing after the new subpage bundles are configured.
 */
function ghi_subpages_deploy_reindex_operation_pages() {
  $index = \Drupal::entityTypeManager()->getStorage('search_api_index')->load('solr');
  if (!$index) {
    return (string) t('Operation page search index was not available for reindexing.');
  }
  $index->reindex();
  return (string) t('Scheduled operation page search index reindexing.');
}
