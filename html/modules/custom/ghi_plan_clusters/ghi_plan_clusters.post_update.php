<?php

/**
 * @file
 * Post-update hooks for the GHI Plan Clusters module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function ghi_plan_clusters_removed_post_updates(): array {
  return [
    'ghi_plan_clusters_post_update_assure_section_reference' => 'GHI v1.21.0',
    'ghi_plan_clusters_post_update_remove_invalid_plan_clusters' => 'GHI v1.21.0',
  ];
}
