<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Component\Render\FormattableMarkup;

/**
 * Adds table-specific guidance to configurable financial labels.
 */
trait FinancialTableItemTrait {

  /**
   * Adds the currency-unit hint only to currency columns inside tables.
   */
  protected function addFinancialTableLabelHint(array $element, bool $uses_currency): array {
    // Financial items are reused in non-table components; the submitted form
    // parents identify when the item is configuring a table column.
    $is_table_column = in_array('columns', $element['#parents'] ?? [], TRUE);
    if (!$uses_currency || !$is_table_column || empty($element['label'])) {
      return $element;
    }

    $hint = $this->t('Do not include a currency unit such as "($)" in a table column label. Excel downloads add it automatically.');
    $description = $element['label']['#description'] ?? NULL;
    $element['label']['#description'] = $description ? new FormattableMarkup('@description<br>@hint', [
      '@description' => $description,
      '@hint' => $hint,
    ]) : $hint;
    return $element;
  }

}
