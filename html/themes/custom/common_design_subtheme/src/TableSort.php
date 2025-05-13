<?php

namespace Drupal\common_design_subtheme;

use Drupal\Core\Template\Attribute;
use Drupal\hpc_api\Query\EndpointQuery;

/**
 * Class for handling table sorting.
 */
class TableSort {

  /**
   * Sort table rows.
   *
   * This method tries to be the PHP mimic what sorttable.js does in the
   * frontend.
   *
   * @param array $header
   *   An array of header rows.
   * @param array $rows
   *   An array of table rows.
   * @param int $column
   *   The column index to sort by.
   * @param string $direction
   *   The sort direction as a string.
   */
  public static function sort(array &$header, array &$rows, int $column, ?string $direction = 'asc') {
    $sort_factor = 1;
    $key = array_keys($header)[$column];
    if (!empty($header[$key]['attributes']) && $header[$key]['attributes'] instanceof Attribute) {
      /** @var \Drupal\Core\Template\Attribute $attributes */
      $attributes = &$header[$key]['attributes'];
      $column_type = (string) ($attributes['data-column-type'] ?? 'string');
      $numeric_columns = [
        'number',
        'amount',
        'currency',
        'percentage',
      ];
      if (in_array($column_type, $numeric_columns)) {
        $sort_factor = -1;

      }
      $attributes->addClass($direction == 'asc' ? 'sorttable-sorted' : 'sorttable-sorted-reverse');
    }

    $sort_factor = $sort_factor * ($direction == strtolower(EndpointQuery::SORT_ASC) ? 1 : -1);
    usort($rows, function ($a, $b) use ($column, $sort_factor) {
      $content_a = self::extractValue($a, $column);
      $content_b = self::extractValue($b, $column);

      if ($content_a === NULL || $content_b === NULL) {
        // If we still have no value at this point, bail out.
        return NULL;
      }
      if (is_numeric($content_a) && is_numeric($content_b)) {
        return $content_a <=> $content_b;
      }
      return $sort_factor * strnatcasecmp($content_a, $content_b);
    });

    return TRUE;
  }

  /**
   * Extract a scalar value from a column row.
   *
   * @param array $row
   *   The row array.
   * @param int $column
   *   The column index for which to extract the value.
   *
   * @return string|int|null
   *   The scalar value for the column, or NULL if all else fails.
   */
  private static function extractValue($row, $column) {
    $cell = array_values($row['cells'])[$column] ?? NULL;
    if ($cell === NULL) {
      return 0;
    }
    if (is_array($cell) && ($cell['attributes'] ?? NULL) instanceof Attribute) {
      if ($cell['attributes']->hasAttribute('data-sort-value')) {
        return $cell['attributes']['data-sort-value'];
      }
      elseif ($cell['attributes']->hasAttribute('data-raw-value')) {
        return $cell['attributes']['data-raw-value'];
      }
    }

    if (!array_key_exists('content', $cell)) {
      return 0;
    }
    $cell_content = $cell['content'];
    $cell_content = is_array($cell_content) ? $cell_content['value']['content'] : $cell_content;
    $cell_content = is_array($cell_content) && array_key_exists(0, $cell_content) ? $cell_content[0] : $cell_content;
    $cell_content = is_array($cell_content) && array_key_exists('#title', $cell_content) ? $cell_content['#title'] : $cell_content;
    $cell_content = is_array($cell_content) && array_key_exists('name', $cell_content) ? $cell_content['name'] : $cell_content;
    $cell_content = is_array($cell_content) && array_key_exists('#markup', $cell_content) ? $cell_content['#markup'] : $cell_content;
    $cell_content = is_array($cell_content) && empty($cell_content) ? '' : $cell_content;

    return is_array($cell_content) ? NULL : $cell_content;
  }

}
