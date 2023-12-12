<?php

/**
 * @file
 * Views plugin definitions.
 */

/**
 * Implements hook_views_data().
 */
function ghi_embargoed_access_views_data() {
  $data = [];
  $data['node']['protected_status'] = [
    'field' => [
      'title' => t('Protected status (Deprecated)'),
      'help' => t('Get the protected status of a node.'),
      'id' => 'protected_status',
    ],
  ];
  return $data;
}
