<?php

/**
 * @file
 * Deploy functions for GHI Custom Subpages.
 */

/**
 * Check if an update has already been run.
 *
 * @param string $name
 *   The name of the update.
 *
 * @return bool
 *   TRUE if it has already run, FALSE otherwise.
 */
function ghi_subpages_custom_update_already_run($name) {
  return in_array('ghi_subpages_custom_post_update_' . $name, \Drupal::keyValue('post_update')->get('existing_updates'));
}

/**
 * Move existing "document" content to "custom_subpage".
 */
function ghi_subpages_custom_deploy_move_existing_content(&$sandbox) {
  if (ghi_subpages_custom_update_already_run('move_existing_content')) {
    return;
  }
  // Get existing content of type "document".
  $documents = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'document',
  ]);
  // Make sure each of them gets moved to custom subpages.
  foreach ($documents as $document) {
    $existing_custom_pages = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties(array_filter([
      'type' => 'custom_subpage',
      'title' => $document->label(),
      'field_entity_reference' => $document->get('field_entity_reference')->target_id,
      'field_team' => $document->get('field_team')->target_id,
    ]));
    if (!empty($existing_custom_pages)) {
      continue;
    }
    $document->set('type', 'custom_subpage');
    $document->save();
  }

  // Recreate the url aliases.
  drupal_flush_all_caches();
  \Drupal::service('pathauto.generator')->resetCaches();
  $documents = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
    'type' => 'custom_subpage',
  ]);
  foreach ($documents as $document) {
    $document->save();
  }
}
