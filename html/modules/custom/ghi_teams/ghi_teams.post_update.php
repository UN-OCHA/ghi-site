<?php

/**
 * @file
 * Post update functions for GHI Teams.
 */

use Drupal\user\Entity\User;

/**
 * Assign new roles.
 */
function ghi_teams_post_update_set_new_roles() {
  $uids_editor = \Drupal::entityTypeManager()
    ->getStorage('user')
    ->getQuery()
    ->condition('roles', 'editor')
    ->accessCheck(FALSE)
    ->execute();
  if (empty($uids_editor)) {
    return;
  }
  foreach (User::loadMultiple($uids_editor) as $account) {
    $account->removeRole('editor');
    $account->addRole('global_editor');
    $account->save();
  }
}
