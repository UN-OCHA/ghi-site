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
   * Turn a camelcase string to an underscore separated string.
   *
   * @param string $string
   *   The input string.
   *
   * @return string
   *   The output string.
   */
  public static function camelCaseToUnderscoreCase($string) {
    return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $string));
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
