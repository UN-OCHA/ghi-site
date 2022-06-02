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
  public static function makeCamelCase($string, $initial_lower_case) {
    $string = str_replace('_', '', ucwords($string, '_'));
    if ($initial_lower_case) {
      $string = lcfirst($string);
    }
    return $string;
  }

  /**
   * Get an abbreviation for a string.
   */
  public static function getAbbreviation($string) {
    if (strpos($string, ' ') === FALSE) {
      return $string;
    }
    $words = explode(' ', $string);
    $abbreviation = implode('', array_map(function ($word) {
      return $word[0];
    }, $words));
    return strtoupper($abbreviation);
  }

  /**
   * Render a string.
   */
  public static function renderString($string, $is_export) {
    return $is_export ? trim(Html::decodeEntities(strip_tags($string))) : $string;
  }

}
