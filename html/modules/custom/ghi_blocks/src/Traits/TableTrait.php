<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Helper trait for tables.
 */
trait TableTrait {

  /**
   * Build a column header.
   *
   * @param string $label
   *   The column label.
   * @param string $type
   *   A string identifying the type of data.
   * @param bool $sortable
   *   Whether the column should be sortable.
   *
   * @return array
   *   An array.
   */
  public function buildHeaderColumn($label, $type, $sortable = TRUE) {
    return [
      'data' => $label,
      'data-column-type' => $type,
      'sortable' => $sortable,
    ];
  }

}
