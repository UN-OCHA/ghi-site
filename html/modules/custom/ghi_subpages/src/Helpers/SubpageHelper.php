<?php

namespace Drupal\ghi_subpages\Helpers;

/**
 * Helper class for subpages.
 */
class SubpageHelper {

  /**
   * Get the subpage manager.
   *
   * @return \Drupal\ghi_subpages\SubpageManager
   *   The subpage manager class.
   */
  public static function getSubpageManager() {
    return \Drupal::service('ghi_subpages.manager');
  }

}
