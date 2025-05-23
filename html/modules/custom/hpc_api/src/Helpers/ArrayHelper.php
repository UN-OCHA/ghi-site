<?php

namespace Drupal\hpc_api\Helpers;

use Drupal\hpc_api\Query\EndpointQuery;

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
          if (count($properties) == ($i + 1) && !is_array($value) && ($obj->{$p} ?? NULL) != $value) {
            $found = FALSE;
          }
          elseif (count($properties) == ($i + 1) && is_array($value) && !in_array(($obj->{$p} ?? NULL), $value)) {
            $found = FALSE;
          }

          if (count($properties) > ($i + 1)) {
            if (is_object($obj)) {
              if (isset($obj->{$p})) {
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

  /**
   * Filter an array by the checking if the given properties exist on objects.
   *
   * @param array $array
   *   An array of objects.
   * @param array $properties
   *   The name of the properties to filter for.
   *
   * @return array
   *   The filtered array.
   */
  public static function filterArrayByProperties(array $array, array $properties) {
    return array_filter($array, function ($item) use ($properties) {
      foreach ($properties as $prop) {
        if (empty($item->$prop)) {
          return FALSE;
        }
      }
      return TRUE;
    });
  }

  /**
   * Filter an array by the given search array.
   *
   * @param array $array
   *   An array of arrays.
   * @param array $search_array
   *   The key-value pairs to filter for.
   *
   * @return array
   *   The filtered array.
   */
  public static function filterArrayBySearchArray(array $array, array $search_array) {
    return array_filter($array, function ($item) use ($search_array) {
      foreach ($search_array as $key => $value) {
        if (empty($item[$key])) {
          return FALSE;
        }
        if (is_array($value) && !in_array($item[$key], $value)) {
          return FALSE;
        }
        elseif ($item[$key] != $value) {
          return FALSE;
        }
      }
      return TRUE;
    });
  }

  /**
   * Sort an array by a key that represents a string.
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   * @param int $sort_type
   *   The sort strategy to use.
   */
  public static function sortArray(array &$data, $order, $sort, $sort_type = SORT_NUMERIC) {
    switch ($sort_type) {
      case SORT_NUMERIC:
        self::sortArrayByNumericKey($data, $order, $sort);
        break;

      case SORT_STRING:
        self::sortArrayByStringKey($data, $order, $sort);
        break;

      default:
        throw new InvalidArgumentException('Invalid argument ' . $sort_type . ' for sort type. sortArray function only accepts sort types SORT_NUMERIC and SORT_STRING.');
    }
  }

  /**
   * Sort an array by a key that represents an integer.
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   */
  public static function sortArrayByNumericKey(array &$data, $order, $sort) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($data, function ($a, $b) use ($order, $sort_factor) {
      $a_value = $a[$order] ?? 0;
      $b_value = $b[$order] ?? 0;
      return $sort_factor * ($a_value - $b_value);
    });
  }

  /**
   * Sort an array by a key that represents a string.
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   */
  public static function sortArrayByStringKey(array &$data, $order, $sort = EndpointQuery::SORT_ASC) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($data, function ($a, $b) use ($order, $sort_factor) {
      $a_value = $a[$order] ?? '';
      $b_value = $b[$order] ?? '';
      return $sort_factor * strcasecmp($a_value, $b_value);
    });
  }

  /**
   * Sort an array by progress relative to on a given total.
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   * @param int $total
   *   The total value for the progress calculation.
   */
  public static function sortArrayByProgress(array &$data, $order, $sort, $total) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($data, function ($a, $b) use ($sort_factor, $order, $total) {
      $a_funding = !empty($a[$order]) ? $a[$order] : 0;
      $b_funding = !empty($b[$order]) ? $b[$order] : 0;

      $a_value = $a_funding > 0 ? $a_funding / $total : 0;
      $b_value = $b_funding > 0 ? $b_funding / $total : 0;
      return $sort_factor * ($a_value <=> $b_value);
    });
  }

  /**
   * Sort an array by a key that represents a composite array.
   *
   * This is something like this:
   *
   * $data = [
   *   ...
   *   organizations => [
   *     0 => "8:International Organization for Migration"
   *     1 => "2567:World Vision International"
   *   ]
   *   ...
   * ]
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   */
  public static function sortArrayByCompositeArrayKey(array &$data, $order, $sort) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($data, function ($a, $b) use ($order, $sort_factor) {
      // Now prepare the data. The sort key can potentially contain multiple
      // entries. What we do is this:
      // 1. Sort the values inside the data property
      // 2. Use the first item inside the data property for further sorting.
      //
      // Step 1: Sorting inside the properties.
      $a_item = $a[$order] ?? '';
      $b_item = $b[$order] ?? '';
      uasort($a_item, function ($a, $b) use ($sort_factor) {
        [, $a_value] = explode(':', $a);
        [, $b_value] = explode(':', $b);
        return $sort_factor * strnatcasecmp($a_value, $b_value);
      });
      uasort($b_item, function ($a, $b) use ($sort_factor) {
        [, $a_value] = explode(':', $a);
        [, $b_value] = explode(':', $b);
        return $sort_factor * strnatcasecmp($a_value, $b_value);
      });
      // Step 2: Now we have prepared values to use for the actual sorting.
      [, $a_value] = explode(':', $a_item[0]);
      [, $b_value] = explode(':', $b_item[0]);
      return $sort_factor * strnatcasecmp($a_value, $b_value);
    });
  }

  /**
   * Sort an array by key that represents supported project fields for a plan.
   *
   * @param array $data
   *   The data array to sort.
   * @param string $order
   *   The array key to sort by.
   * @param string $sort
   *   The sort direction.
   * @param string $object_list
   *   The array key that holds the object list.
   * @param string $search_property
   *   The property to search for.
   * @param string $value_property
   *   The property to use for sorting.
   */
  public static function sortArrayByObjectListProperty(array &$data, $order, $sort, $object_list = 'fields', $search_property = 'name', $value_property = 'value') {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($data, function ($a, $b) use ($order, $sort_factor, $object_list, $search_property, $value_property) {
      if (!empty($a[$object_list]) && !empty($b[$object_list])) {
        $x = '';
        $y = '';
        // Ensure you get value of correct project property.
        foreach ($a[$object_list] as $key => $value) {
          if ($a[$object_list][$key]->$search_property == $order) {
            $x = $a[$object_list][$key]->$value_property;
          }
        }
        foreach ($b[$object_list] as $index => $val) {
          if ($b[$object_list][$index]->$search_property == $order) {
            $y = $b[$object_list][$index]->$value_property;
          }
        }
        return $sort_factor * strnatcasecmp($x, $y);
      }
    });
  }

  /**
   * Combine two arrays by summing up by the specified sub key.
   *
   * @param array $array
   *   An array of arrays.
   * @param string $key
   *   The array key that should be summed up.
   *
   * @return int
   *   The grand total.
   */
  public static function sumArraysByKey(array $array, $key) {
    $sum = 0;
    if (empty($array)) {
      return $sum;
    }
    foreach ($array as $item) {
      if (empty($item[$key])) {
        continue;
      }
      $sum += $item[$key];
    }
    return $sum;
  }

  /**
   * Find the first item in an array by properties.
   *
   * @param array $array
   *   An array of objects.
   * @param array $properties
   *   The name of the properties to match for.
   *
   * @return array|object
   *   The first array or object matching the properties.
   */
  public static function findFirstItemByProperties(array $array, array $properties) {
    $candidates = array_filter($array, function ($item) use ($properties) {
      $item = (object) $item;
      foreach ($properties as $key => $value) {
        if (empty($item->$key)) {
          return FALSE;
        }
        if ($item->$key != $value) {
          return FALSE;
        }
      }
      return TRUE;
    });
    return !empty($candidates) ? reset($candidates) : NULL;
  }

  /**
   * Add an item to an associative array at the specified position.
   *
   * @param array $array
   *   The array to extend.
   * @param string $key
   *   The array key for the new item.
   * @param mixed $value
   *   The array value for the new item.
   * @param int $pos
   *   Optionally, specify the position in the array, after which the item
   *   should be added. Zero-based.
   */
  public static function extendAssociativeArray(array &$array, $key, $value, $pos = NULL) {
    if ($pos === NULL || count($array) < $pos + 1) {
      $array[$key] = $value;
    }
    else {
      $first_part = array_slice($array, 0, $pos, TRUE);
      $last_part = array_slice($array, $pos, count($array) - $pos, TRUE);
      $array = $first_part + [$key => $value] + $last_part;
    }
  }

  /**
   * Combine two arrays by summing up by the specified sub key.
   *
   * @param array $array
   *   An array of objects.
   * @param string $property
   *   The object property that should be summed up.
   *
   * @return int
   *   The grand total.
   */
  public static function sumObjectsByProperty(array $array, $property) {
    $sum = 0;
    if (empty($array)) {
      return $sum;
    }
    foreach ($array as $item) {
      if (empty($item->$property)) {
        continue;
      }
      $sum += $item->$property;
    }
    return $sum;
  }

  /**
   * Sort an array of objects by the given callback function.
   *
   * @param array $array
   *   An array of objects.
   * @param string $method
   *   The name of the method that should be used for sorting.
   * @param string $sort
   *   The sort direction.
   * @param int $sort_type
   *   The sort direction, either SORT_NUMERIC or SORT_STRING.
   */
  public static function sortObjectsByMethod(array &$array, string $method, $sort = EndpointQuery::SORT_ASC, $sort_type = SORT_NUMERIC) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($array, function ($a, $b) use ($method, $sort_factor, $sort_type) {
      $default = $sort_type == SORT_NUMERIC ? 0 : '';
      $a_value = method_exists($a, $method) ? (call_user_func([$a, $method]) ?? $default) : $default;
      $b_value = method_exists($b, $method) ? (call_user_func([$b, $method]) ?? $default) : $default;
      return $sort_factor * ($sort_type == SORT_NUMERIC ? $a_value - $b_value : strnatcasecmp($a_value, $b_value));
    });
  }

  /**
   * Sort an array of objects by the given callback function.
   *
   * @param array $array
   *   An array of objects.
   * @param callable $callback
   *   The callback that should be used for sorting.
   * @param string $sort
   *   The sort direction.
   * @param int $sort_type
   *   The sort direction, either SORT_NUMERIC or SORT_STRING.
   */
  public static function sortObjectsByCallback(array &$array, callable $callback, $sort = EndpointQuery::SORT_ASC, $sort_type = SORT_NUMERIC) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($array, function ($a, $b) use ($callback, $sort_factor, $sort_type) {
      $default = $sort_type == SORT_NUMERIC ? 0 : '';
      $a_value = $callback($a) ?? $default;
      $b_value = $callback($b) ?? $default;
      return $sort_factor * ($sort_type == SORT_NUMERIC ? $a_value - $b_value : strnatcasecmp($a_value, $b_value));
    });
  }

  /**
   * Sort an array of objects by the given property.
   *
   * @param array $array
   *   An array of objects.
   * @param string|callable $property
   *   The object property or method that should be sorted by.
   * @param string $sort
   *   The sort direction.
   * @param int $sort_type
   *   The sort direction, either SORT_NUMERIC or SORT_STRING.
   */
  public static function sortObjectsByProperty(array &$array, $property, $sort = EndpointQuery::SORT_ASC, $sort_type = SORT_NUMERIC) {
    switch ($sort_type) {
      case SORT_NUMERIC:
        self::sortObjectsByNumericProperty($array, $property, $sort);
        break;

      case SORT_STRING:
        self::sortObjectsByStringProperty($array, $property, $sort);
        break;

      default:
        throw new InvalidArgumentException('Invalid argument ' . $sort_type . ' for sort type. sortObjectsByProperty function only accepts sort types SORT_NUMERIC and SORT_STRING.');
    }
  }

  /**
   * Sort an array of objects by the given property using numeric comparision.
   *
   * @param array $array
   *   An array of objects.
   * @param string|callable $property
   *   The object property that should be sorted by.
   * @param string $sort
   *   The sort direction.
   */
  public static function sortObjectsByNumericProperty(array &$array, $property, $sort = EndpointQuery::SORT_ASC) {
    $sort_factor = $sort == EndpointQuery::SORT_DESC ? -1 : 1;
    uasort($array, function ($a, $b) use ($property, $sort_factor) {
      $a_value = method_exists($a, $property) ? ($a->$property() ?? 0) : (!empty($a->$property) ? $a->$property : 0);
      $b_value = method_exists($b, $property) ? ($b->$property() ?? 0) : (!empty($b->$property) ? $b->$property : 0);
      return $sort_factor * ($a_value - $b_value);
    });
  }

  /**
   * Sort an array of objects by the given property using string comparison.
   *
   * @param array $array
   *   An array of objects.
   * @param string|callable $property
   *   The object property that should be sorted by.
   * @param string $sort
   *   The sort direction.
   */
  public static function sortObjectsByStringProperty(array &$array, $property, $sort = EndpointQuery::SORT_ASC) {
    uasort($array, function ($a, $b) use ($property, $sort) {
      $a_value = method_exists($a, $property) ? ($a->$property() ?? '') : (!empty($a->$property) ? $a->$property : '');
      $b_value = method_exists($b, $property) ? ($b->$property() ?? '') : (!empty($b->$property) ? $b->$property : '');
      if ($sort == EndpointQuery::SORT_ASC) {
        return strcasecmp($a_value, $b_value);
      }
      if ($sort == EndpointQuery::SORT_DESC) {
        return strcasecmp($b_value, $a_value);
      }
    });
  }

}
