<?php

namespace Drupal\hpc_common\Helpers;

/**
 * Helper class for user functionality.
 */
class UserHelper {

  /**
   * Check if the current user is an administrator.
   */
  public static function isAdministrator() {
    if (\Drupal::currentUser()->hasPermission('administer site configuration')) {
      return TRUE;
    }
    $user_roles = \Drupal::currentUser()->getRoles();
    return in_array('administrator', $user_roles);
  }

}
