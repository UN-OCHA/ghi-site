<?php

/**
 * @file
 * Post-update hooks for the GHI Menu module.
 */

/**
 * Implements hook_removed_post_updates().
 */
function ghi_menu_removed_post_updates(): array {
  return [
    'ghi_menu_post_update_adjust_admin_menu' => 'GHI v1.21.0',
  ];
}
