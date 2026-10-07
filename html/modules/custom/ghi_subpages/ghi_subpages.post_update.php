<?php

/**
 * @file
 * Post-update hooks for the GHI Subpages module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function ghi_subpages_removed_post_updates(): array {
  return [
    'ghi_subpages_post_update_create_standard_subpages' => 'GHI v1.21.0',
    'ghi_subpages_post_update_queue_logframes' => 'GHI v1.21.0',
    'ghi_subpages_post_update_update_subpage_url_aliases' => 'GHI v1.21.0',
  ];
}
