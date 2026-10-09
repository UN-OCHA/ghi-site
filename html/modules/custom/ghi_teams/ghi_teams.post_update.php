<?php

/**
 * @file
 * Post-update hooks for the GHI Teams module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function ghi_teams_removed_post_updates(): array {
  return [
    'ghi_teams_post_update_set_new_roles' => 'GHI v1.21.0',
  ];
}
