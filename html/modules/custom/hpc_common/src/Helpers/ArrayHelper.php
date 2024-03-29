<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\hpc_api\Helpers\ArrayHelper as ApiArrayHelper;

/**
 * Helper class for array manipulation and traversal.
 */
class ArrayHelper extends ApiArrayHelper {

  /**
   * Swap 2 columns in an associative array.
   *
   * @param array $array
   *   The array to process.
   * @param string|int $key1
   *   The first key.
   * @param string|int $key2
   *   The second key.
   * @param bool $strict
   *   Whether to use strict for finding a key in the array.
   */
  public static function swap(array &$array, $key1, $key2, $strict = FALSE) {
    $keys = array_keys($array);
    if (!array_key_exists($key1, $array) || !array_key_exists($key2, $array)) {
      return FALSE;
    }
    if (($index1 = array_search($key1, $keys, $strict)) === FALSE) {
      return FALSE;
    }
    if (($index2 = array_search($key2, $keys, $strict)) === FALSE) {
      return FALSE;
    }
    [$keys[$index1], $keys[$index2]] = [$key2, $key1];
    [$array[$key1], $array[$key2]] = [$array[$key2], $array[$key1]];
    $array = array_combine($keys, array_values($array));
  }

  /**
   * Do array_map but keep index association.
   *
   * @param callable $callable
   *   The callback.
   * @param array $array
   *   The array to process.
   *
   * @return array
   *   The processed array.
   */
  public static function arrayMapAssoc(callable $callable, array $array) {
    return array_combine(array_keys($array), array_map($callable, $array, array_keys($array)));
  }

  /**
   * Map an array of items to strings.
   *
   * This turns objects into strings to the extent possible.
   *
   * @param array $array
   *   The array to process.
   *
   * @return array
   *   The processed array.
   */
  public static function mapObjectsToString(array $array) {
    foreach ($array as $key => $value) {
      if (is_object($value)) {
        if ($value instanceof \Stringable) {
          $array[$key] = (string) $value;
        }
        else {
          $array[$key] = self::mapObjectsToString((array) $value);
        }
      }
      if (is_array($value)) {
        $array[$key] = self::mapObjectsToString($value);
      }
    }
    return $array;
  }

  /**
   * Sort a multidimensional array (array of arrays) by keys.
   *
   * @param array $array
   *   The input array.
   */
  public static function sortMultiDimensionalArrayByKeys(array &$array) {
    $is_assoc = array_keys($array) !== range(0, count($array) - 1);
    if ($is_assoc) {
      ksort($array);
    }
    else {
      asort($array);
    }
    foreach ($array as &$a) {
      if (is_array($a)) {
        self::sortMultiDimensionalArrayByKeys($a);
      }
    }
  }

  /**
   * Reduce an array by removing empty items.
   *
   * @param array $array
   *   The input array.
   */
  public static function reduceArray(array &$array) {
    foreach ($array as $key => &$a) {
      if (is_array($a)) {
        if (empty($a)) {
          unset($array[$key]);
        }
        else {
          self::reduceArray($a);
        }
      }
    }
    $array = array_filter($array);
  }

}
