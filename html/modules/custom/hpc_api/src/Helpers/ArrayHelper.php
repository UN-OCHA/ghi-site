<?php

namespace Drupal\hpc_api\Helpers;

/**
 * Helper class for array handling.
 */
class ArrayHelper {

  /**
   * Deep filter the given array.
   *
   * Supports array of arrays and array of objects and/or mixed.
   *
   * @param array $array
   *   The original array.
   * @param array $filters
   *   An array with the filters to apply.
   *
   * @return array
   *   The filtered array.
   */
  public static function filterArray(array $array, array $filters) {
    $filtered_array = [];
    foreach ($array as $i => $item) {
      $found = TRUE;

      foreach ($filters as $filter => $value) {
        $properties = explode('.', $filter);
        $obj = (object) $item;

        foreach ($properties as $i => $p) {

          if (count($properties) == ($i + 1) && !is_array($value) && $obj->{$p} != $value) {
            $found = FALSE;
          }
          elseif (count($properties) == ($i + 1) && is_array($value) && !in_array($obj->{$p}, $value)) {
            $found = FALSE;
          }

          if (count($properties) > ($i + 1)) {
            if (is_object($obj)) {
              if (property_exists($obj, $p)) {
                $obj = $obj->{$p};
              }
              else {
                $found = FALSE;
              }
            }
            elseif (is_array($obj)) {
              if (array_key_exists($p, $obj)) {
                $obj = $obj[$p];
              }
              else {
                $found = FALSE;
              }
            }
          }
        }
      }

      if ($found == TRUE) {
        $filtered_array[] = $item;
      }
      $found = FALSE;
    }
    return $filtered_array;
  }

}
