<?php

namespace Drupal\ghi_plans\Helpers;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines shared configuration values and calculations for numeric data points.
 */
final class DataPointHelper {

  public const PROCESSING_SINGLE = 'single';
  public const PROCESSING_CALCULATED = 'calculated';

  public const CALCULATION_PERCENTAGE = 'percentage';
  public const CALCULATION_ADDITION = 'addition';
  // Existing saved configurations use the misspelled value "substraction".
  public const CALCULATION_SUBTRACTION = 'substraction';
  public const CALCULATION_DIVISION = 'division';

  public const FORMATTING_AUTO = 'auto';
  public const FORMATTING_CURRENCY = 'currency';
  public const FORMATTING_AMOUNT = 'amount';
  public const FORMATTING_AMOUNT_ROUNDED = 'amount_rounded';
  public const FORMATTING_PERCENT = 'percent';
  public const FORMATTING_RAW = 'raw';

  /**
   * Gets the shared processing options.
   */
  public static function getProcessingOptions(): array {
    return [
      self::PROCESSING_SINGLE => new TranslatableMarkup('Single metric'),
      self::PROCESSING_CALCULATED => new TranslatableMarkup('Calculated from two metrics'),
    ];
  }

  /**
   * Gets the shared calculation options.
   */
  public static function getCalculationOptions(): array {
    return [
      self::CALCULATION_PERCENTAGE => new TranslatableMarkup('Percentage (metric 1 × (100 ÷ metric 2))'),
      self::CALCULATION_ADDITION => new TranslatableMarkup('Sum (metric 1 + metric 2)'),
      self::CALCULATION_SUBTRACTION => new TranslatableMarkup('Subtraction (metric 1 − metric 2)'),
      self::CALCULATION_DIVISION => new TranslatableMarkup('Division (metric 1 ÷ metric 2)'),
    ];
  }

  /**
   * Gets the shared formatting options.
   *
   * Consumers can override the automatic option because its inference rules
   * depend on the available metric metadata.
   */
  public static function getFormattingOptions(?TranslatableMarkup $automatic_label = NULL): array {
    return [
      self::FORMATTING_AUTO => $automatic_label ?? new TranslatableMarkup('Automatic based on the unit (uses percentage for percentages, amount for all others)'),
      self::FORMATTING_CURRENCY => new TranslatableMarkup('Currency value'),
      self::FORMATTING_AMOUNT => new TranslatableMarkup('Amount value'),
      self::FORMATTING_AMOUNT_ROUNDED => new TranslatableMarkup('Amount value (rounded, 1 decimal)'),
      self::FORMATTING_PERCENT => new TranslatableMarkup('Percentage value'),
      self::FORMATTING_RAW => new TranslatableMarkup('Raw data (no formatting)'),
    ];
  }

  /**
   * Calculates a value using the established data-point operand semantics.
   *
   * Division follows the operand order shown in the configuration interface.
   */
  public static function calculate(int|float|null $value_1, int|float|null $value_2, ?string $calculation): int|float|null {
    if ($value_1 === NULL || $value_2 === NULL) {
      return NULL;
    }
    return match ($calculation) {
      self::CALCULATION_ADDITION => $value_1 + $value_2,
      self::CALCULATION_SUBTRACTION => $value_1 - $value_2,
      self::CALCULATION_DIVISION => $value_2 != 0 ? $value_1 / $value_2 : NULL,
      self::CALCULATION_PERCENTAGE => $value_2 != 0 ? $value_1 / $value_2 : NULL,
      default => NULL,
    };
  }

}
