<?php

/**
 * @file
 * Theme hooks and general purpose theme related functions for HPC Common.
 *
 * Currently empty, but should stay here to enforce code separation.
 */

use Drupal\Core\Render\Markup;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Helper function to wrap the given output into HTML markup.
 *
 * @param string $output
 *   The output string.
 * @param array $vars
 *   The variables array as given to a theme function.
 * @param array|string $classes
 *   An optional array or string of class names for the markup.
 * @param array $attributes
 *   An optional array of attributes for the markup.
 *
 * @return string
 *   Either the pure output or the output string wrapped in markup.
 */
function hpc_common_markup_wrapper($output, array $vars, $classes = NULL, array $attributes = NULL) {
  $markup_element = !empty($vars['markup_element']) ? $vars['markup_element'] : 'span';
  if ($vars['export']) {
    return $output;
  }
  $class = '';
  if (!empty($classes)) {
    $class = 'class="' . (is_array($classes) ? implode(' ', $classes) : $classes) . '"';
  }
  $attr = !empty($attributes) && is_array($attributes) ? drupal_attributes($attributes) : '';
  return '<' . $markup_element . ' ' . $class . ' ' . $attr . '>' . $output . '</' . $markup_element . '>';
}

/**
 * Theme a value using automatic formatting based on the unit.
 */
function hpc_common_preprocess_hpc_autoformat_value(array &$vars) {
  $value = $vars['value'];
  $unit_type = !empty($vars['unit_type']) ? $vars['unit_type'] : 'amount';
  $unit_defaults = $vars['unit_defaults'];
  $formatting_decimals = !empty($vars['formatting_decimals']) ? $vars['formatting_decimals'] : ThemeHelper::DECIMALS_POINT;

  switch ($unit_type) {
    case 'percentage':
      // Using percentage formating.
      $vars['output'] = [
        '#theme' => 'hpc_percent',
        '#ratio' => $value / 100,
        '#formatting_decimals' => $formatting_decimals,
      ] + (!empty($unit_defaults['percentage']) ? $unit_defaults['percentage'] : []);
      break;

    case 'amount':
      // Using amount formating.
      $vars['output'] = [
        '#theme' => 'hpc_amount',
        '#amount' => $value,
        '#formatting_decimals' => $formatting_decimals,
      ] + (!empty($unit_defaults['amount']) ? $unit_defaults['amount'] : []);
      break;
  }
}

/**
 * Preprocess a currceny value.
 */
function hpc_common_preprocess_hpc_currency(array &$vars) {
  $value = $vars['value'];
  $currency = $vars['currency'];
  $scale = $vars['scale'] != 'auto' ? $vars['scale'] : NULL;
  $use_abbreviation = $vars['use_abbreviation'];
  $formatting_decimals = !empty($vars['formatting_decimals']) ? $vars['formatting_decimals'] : ThemeHelper::DECIMALS_POINT;

  $export = $vars['export'];

  // If the value is NULL, print it as 0.
  if (empty($value)) {
    $value = 0;
  }

  if ($export) {
    // No formatting for downloads.
    return $value;
  }

  $locale = 'en-US';
  if (class_exists('NumberFormatter')) {
    $format = new NumberFormatter($locale . "@currency=$currency", NumberFormatter::CURRENCY);
    $currency_symbol = $format->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
  }
  else {
    $currency_symbol = $currency;
  }

  $billion = pow(10, 9);
  $million = pow(10, 6);

  $vars['output'] = Markup::create($currency_symbol . ThemeHelper::theme('hpc_amount', [
    '#amount' => $value,
    '#scale' => $scale,
    '#export' => $export,
    '#use_abbreviation' => $use_abbreviation,
    '#decimals' => $value > $billion ? 2 : ($value > $million / 2 ? 1 : 0),
    '#formatting_decimals' => $formatting_decimals,
  ]));
}

/**
 * Preprocess an amount, e.g. for people in need.
 *
 * For billions, the amounts will display as "x.x bn".
 * For millions, the amounts will display as "x.x m".
 */
function hpc_common_preprocess_hpc_amount(array &$vars) {
  $amount = $vars['amount'];
  $scale = $vars['scale'] != 'auto' ? $vars['scale'] : NULL;
  $suffix = $vars['suffix'];
  $use_abbreviation = $vars['use_abbreviation'];
  $include_suffix = $vars['include_suffix'];
  $export = $vars['export'];
  $decimals = $vars['decimals'];

  $formatting_decimals = !empty($vars['formatting_decimals']) ? $vars['formatting_decimals'] : ThemeHelper::DECIMALS_POINT;

  if (!is_numeric($amount)) {
    $vars['output'] = $amount;
    return;
  }

  // If the value is NULL, print it as 0.
  if (empty($amount)) {
    $amount = 0;
  }

  if ($scale == 'raw' || $export) {
    $vars['output'] = $amount;
    return;
  }

  $output = '';
  $billion = pow(10, 9);
  $million = pow(10, 6);
  $thousand = pow(10, 3);

  if ((empty($scale) && ($amount / $billion) >= 1) || $scale == ThemeHelper::SCALE_BILLION) {
    // If the amount is in the billions, give it the correct formatting.
    $scaled_amount = round($amount / $billion, $decimals);
    $suffix = ThemeHelper::getNumberSuffix(ThemeHelper::SCALE_BILLION, $use_abbreviation);
  }
  elseif ((empty($scale) && ($amount / $million) >= 0.5) || $scale == ThemeHelper::SCALE_MILLION) {
    // If the amount is in the millions, give it the correct formatting.
    $scaled_amount = round($amount / $million, $decimals);
    $suffix = ThemeHelper::getNumberSuffix(ThemeHelper::SCALE_MILLION, $use_abbreviation);
  }
  elseif ((empty($scale) && ($amount / $thousand) >= 0.5) || $scale == ThemeHelper::SCALE_THOUSAND) {
    // If the amount is in the thousands, give it the correct formatting.
    $scaled_amount = round($amount / $thousand, $decimals);
    $suffix = ThemeHelper::getNumberSuffix(ThemeHelper::SCALE_THOUSAND, $use_abbreviation);
  }
  elseif (empty($scale) || $scale == 'full') {
    // The amount should be displayed in full, so no additional formatting
    // necessary.
    $scaled_amount = $amount;
  }
  else {
    $scaled_amount = $amount;
  }

  $dec_point = $formatting_decimals == ThemeHelper::DECIMALS_POINT ? '.' : ',';
  $thousands_sep = $formatting_decimals == ThemeHelper::DECIMALS_POINT ? ',' : ' ';
  $formatted_amount = number_format($scaled_amount, $decimals, $dec_point, $thousands_sep);

  // If the value ends with ".0", then it is unnecessary
  // precision, so we remove it.
  if (strpos($formatted_amount, $dec_point . '0') && substr($formatted_amount, strlen($formatted_amount) - 2) == $dec_point . '0') {
    $formatted_amount = rtrim($formatted_amount, '0');
    $formatted_amount = rtrim($formatted_amount, $dec_point);
  }

  $output .= $formatted_amount;

  if ($include_suffix && !empty($suffix)) {
    // If the suffix is explicetly requested we add a space between the amount
    // and the suffix.
    $space = (!empty($vars['suffix']) ? '&nbsp' : '');
    $output .= $export ? $suffix : $space . '<span class="suffix">' . $suffix . '</span>';
  }

  $vars['output'] = Markup::create($output);
  $vars['class'] = implode(' ', ['number', 'amount']);
  $vars['attributes'] = ['data-value' => $amount];
}

/**
 * Theme a percent value.
 */
function hpc_common_preprocess_hpc_percent(array &$vars) {
  $export = $vars['export'];
  $formatting_decimals = !empty($vars['formatting_decimals']) ? $vars['formatting_decimals'] : ThemeHelper::DECIMALS_POINT;
  $precision = $vars['precision'];
  if (!empty($vars['percent'])) {
    $percent = $vars['percent'];
  }
  else {
    $percent = ((float) $vars['ratio']) * 100;
  }
  $precision = !empty($precision) ? $precision : ($percent == intval($percent) ? 0 : 1);
  // Percentages higher than 100 don't need precision. Numbers like 151.2% also
  // produce visual problems sometimes, e.g. in tables.
  if ($percent > 100) {
    $precision = 0;
  }
  $dec_point = $formatting_decimals == ThemeHelper::DECIMALS_POINT ? '.' : ',';
  $thousands_sep = $formatting_decimals == ThemeHelper::DECIMALS_POINT ? ',' : '.';
  $formatted = number_format($percent, $precision, $dec_point, $thousands_sep);
  $suffix = $export ? '%' : '<span class="suffix">%</span>';

  $vars['output'] = Markup::create($formatted . $suffix);
  $vars['class'] = implode(' ', ['number', 'percent']);
  $vars['attributes'] = ['data-value' => $percent];
}
