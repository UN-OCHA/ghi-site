<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;

/**
 * Provides shared presentation details for financial labels.
 */
final class FinancialLabel {

  public const TRANSLATION_CONTEXT = 'Financial table label';

  /**
   * Adds the invariant currency unit without creating another translation.
   *
   * @param string|\Drupal\Component\Render\MarkupInterface $label
   *   A translated, unit-free label.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The label with its currency unit.
   */
  public static function withCurrencyUnit(string|MarkupInterface $label): FormattableMarkup {
    return new FormattableMarkup('@label ($)', ['@label' => $label]);
  }

}
