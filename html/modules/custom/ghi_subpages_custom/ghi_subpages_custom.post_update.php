<?php

/**
 * @file
 * Post-update hooks for the GHI Custom Subpages module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function ghi_subpages_custom_removed_post_updates(): array {
  return [
    'ghi_subpages_custom_post_update_move_existing_content' => 'GHI v1.21.0',
  ];
}
