<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Utility\Html;

/**
 * Helper class for string handling.
 */
class StringHelper {

  /**
   * Make a string camel case.
   */
  public static function makeCamelCase($string, $initial_lower_case = FALSE) {
    $string = str_replace('_', '', ucwords($string, '_'));
    if ($initial_lower_case) {
      $string = lcfirst($string);
    }
    return $string;
  }

  /**
   * Render a string.
   */
  public static function renderString($string, $is_export = TRUE) {
    return $is_export ? trim(Html::decodeEntities(strip_tags($string))) : $string;
  }

}
