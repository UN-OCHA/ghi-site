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
      $column_a = array_values($a['cells'])[$column];
      $column_b = array_values($b['cells'])[$column];
      $content_a = ($column_a['attributes'] ?? NULL) instanceof Attribute && $column_a['attributes']->hasAttribute('data-raw-value') ? $column_a['attributes']['data-raw-value'] : $column_a['content'];
      $content_b = ($column_b['attributes'] ?? NULL) instanceof Attribute && $column_b['attributes']->hasAttribute('data-raw-value') ? $column_b['attributes']['data-raw-value'] : $column_b['content'];
      $content_a = is_array($content_a) ? $content_a['value']['content'] : $content_a;
      $content_b = is_array($content_b) ? $content_b['value']['content'] : $content_b;
      $content_a = is_array($content_a) && array_key_exists(0, $content_a) ? $content_a[0] : $content_a;
      $content_b = is_array($content_b) && array_key_exists(0, $content_b) ? $content_b[0] : $content_b;

      $content_a = is_array($content_a) && array_key_exists('#title', $content_a) ? $content_a['#title'] : $content_a;
      $content_b = is_array($content_b) && array_key_exists('#title', $content_b) ? $content_b['#title'] : $content_b;
      $content_a = is_array($content_a) && array_key_exists('name', $content_a) ? $content_a['name'] : $content_a;
      $content_b = is_array($content_b) && array_key_exists('name', $content_b) ? $content_b['name'] : $content_b;
      $content_a = is_array($content_a) && array_key_exists('#markup', $content_a) ? $content_a['#markup'] : $content_a;
      $content_b = is_array($content_b) && array_key_exists('#markup', $content_b) ? $content_b['#markup'] : $content_b;
      $content_a = is_array($content_a) && empty($content_a) ? '' : $content_a;
      $content_b = is_array($content_b) && empty($content_b) ? '' : $content_b;
      if (is_array($content_a) || is_array($content_b)) {
        // If we still have an array here, bail out.
        return NULL;
      }
      return $sort_factor * strnatcasecmp($content_a, $content_b);
    });

    return TRUE;
  }

}
