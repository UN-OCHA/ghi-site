<?php

/**
 * @file
 * Preprocess hooks and general purpose theme related functions for HPC Common.
 */

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Preprocess a value using automatic formatting based on the unit.
 */
function hpc_common_preprocess_hpc_autoformat_value(array &$vars) {
  $value = $vars['value'];
  $unit_type = !empty($vars['unit_type']) ? $vars['unit_type'] : 'amount';

  $unit_defaults = $vars['unit_defaults'];
  $decimal_format = !empty($vars['decimal_format']) ? $vars['decimal_format'] : ThemeHelper::DECIMALS_POINT;

  switch ($unit_type) {
    case 'percentage':
      // Using percentage formating.
      $vars['output'] = ThemeHelper::render([
        '#theme' => 'hpc_percent',
        '#ratio' => $value / 100,
        '#decimal_format' => $decimal_format,
      ] + (!empty($unit_defaults['percentage']) ? $unit_defaults['percentage'] : []));
      break;

    case 'amount':
      // Using amount formating.
      $vars['output'] = ThemeHelper::render([
        '#theme' => 'hpc_amount',
        '#amount' => $value,
        '#decimal_format' => $decimal_format,
      ] + (!empty($unit_defaults['amount']) ? $unit_defaults['amount'] : []));
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
  $decimal_format = !empty($vars['decimal_format']) ? $vars['decimal_format'] : ThemeHelper::DECIMALS_POINT;

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
    '#decimal_format' => $decimal_format,
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

  $decimal_format = !empty($vars['decimal_format']) ? $vars['decimal_format'] : ThemeHelper::DECIMALS_POINT;

  if ($amount !== NULL && !is_numeric($amount)) {
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

  $dec_point = $decimal_format == ThemeHelper::DECIMALS_POINT ? '.' : ',';
  $thousands_sep = $decimal_format == ThemeHelper::DECIMALS_POINT ? ',' : ' ';
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

  $vars['output'] = Markup::create(trim($output));
  $vars['class'] = implode(' ', ['number', 'amount']);
  $vars['attributes'] = ['data-value' => $amount];
}

/**
 * Preprocess a percent value.
 */
function hpc_common_preprocess_hpc_percent(array &$vars) {
  $export = $vars['export'];
  $decimal_format = !empty($vars['decimal_format']) ? $vars['decimal_format'] : ThemeHelper::DECIMALS_POINT;
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
  $dec_point = $decimal_format == ThemeHelper::DECIMALS_POINT ? '.' : ',';
  $thousands_sep = $decimal_format == ThemeHelper::DECIMALS_POINT ? ',' : '.';
  $formatted = number_format($percent, $precision, $dec_point, $thousands_sep);
  $suffix = $export ? '%' : '<span class="suffix">%</span>';

  $vars['output'] = Markup::create(trim($formatted . $suffix));
  $vars['class'] = implode(' ', ['number', 'percent']);
  $vars['attributes'] = ['data-value' => $percent];
}

/**
 * Preprocess a progress bar.
 */
function hpc_common_preprocess_hpc_progress_bar(array &$vars) {
  $export = $vars['export'];
  $base_ratio = (float) $vars['base_ratio'];
  $vars['percent'] = ((float) ($vars['percent'] ?? ($vars['ratio'] * 100)));
  $vars['precision'] = 1;
  $vars['formatted_value'] = $vars['percent'] !== NULL && !$export ? ThemeHelper::theme('hpc_percent', ThemeHelper::getThemeOptions('hpc_percent', $vars['percent'], $vars)) : t('N/A');

  $wrapper_attributes = new Attribute();
  $wrapper_attributes->addClass('hpc-progress-bar-wrapper');

  if (!empty($base_ratio)) {
    $wrapper_attributes->setAttribute('style', 'width: ' . $base_ratio . '%');
  }
  $vars['wrapper_attributes'] = $wrapper_attributes;
}

/**
 * Preprocess a progress bar.
 */
function hpc_common_preprocess_hpc_pie_chart(array &$vars) {
  $export = $vars['export'];
  $vars['percent'] = ((float) ($vars['percent'] ?? ($vars['ratio'] * 100)));
  $vars['precision'] = 1;
  $vars['formatted_value'] = $vars['percent'] !== NULL && !$export ? ThemeHelper::theme('hpc_percent', ThemeHelper::getThemeOptions('hpc_percent', $vars['percent'], $vars)) : t('N/A');

  $pie_value = round($vars['percent'], 2);
  $pie_value = $pie_value == 0 ? 0.01 : $pie_value;
  $vars['pie_value'] = $pie_value;

  $wrapper_attributes = new Attribute();
  $wrapper_attributes->addClass('hpc-pie-chart-wrapper');
  $vars['wrapper_attributes'] = $wrapper_attributes;
}

/**
 * Theme a sparkline chart.
 */
function hpc_common_preprocess_hpc_sparkline(&$vars) {
  $vars['#attached']['library'][] = 'hpc_common/sparkline';
  $data = $vars['data'] ?? [];
  $tooltips = $vars['tooltips'] ?? [];
  $baseline_value = $vars['baseline_value'];

  $wrapper_attributes = new Attribute();
  $wrapper_attributes->addClass('hpc-pie-chart-wrapper');
  $wrapper_attributes->setAttribute('data-values', implode(',', $data));
  $wrapper_attributes->setAttribute('data-tooltips', implode('|', $tooltips));
  if ($baseline_value) {
    $wrapper_attributes->setAttribute('data-baseline', $baseline_value);
  }
  $vars['wrapper_attributes'] = $wrapper_attributes;
}

/**
 * Preprocess a tooltip.
 */
function hpc_common_preprocess_hpc_tooltip(array &$vars) {
  $vars['#attached']['library'][] = 'hpc_common/tooltip';

  $attributes = new Attribute();
  $attributes->setAttribute('data-toggle', 'tooltip');
  $attributes->setAttribute('tabindex', 0);

  $attributes->addClass('tooltip');
  if (!empty($vars['class'])) {
    $attributes->addClass($vars['class']);
  }

  if (empty($vars['tag'])) {
    $vars['tag'] = 'i';
  }

  if (empty($vars['tag_content'])) {
    $vars['tag_content'] = Markup::create('<svg class="cd-icon icon cd-icon--about"><use xlink:href="#cd-icon--about"></use></svg>');
    $attributes->addClass('info');
  }

  $vars['attributes'] = $attributes;
}

/**
 * Preprocess a popover.
 */
function hpc_common_preprocess_hpc_popover(array &$vars) {
  $vars['#attached']['library'][] = 'hpc_common/popover';

  $trigger_attributes = new Attribute();
  $content_attributes = new Attribute();

  $trigger_attributes->setAttribute('data-toggle', 'popover');
  $trigger_attributes->setAttribute('data-trigger', !empty($vars['trigger']) ? $vars['trigger'] : 'manual');
  if (!empty($vars['placement'])) {
    $trigger_attributes->setAttribute('data-placement', $vars['placement']);
  }

  $trigger_attributes->addClass('trigger');
  if (!empty($vars['class'])) {
    $trigger_attributes->addClass($vars['class']);
    $content_attributes->addClass($vars['class']);
  }
  if (!empty($vars['disabled'])) {
    $trigger_attributes->setAttribute('disabled', 'disabled');
  }
  // Custom markup.
  if (!empty($vars['tag'])) {
    $trigger_attributes->addClass('popover-custom-tag');
  }
  if (!empty($vars['material_icon'])) {
    // Material icons.
    $vars['#attached']['library'][] = 'hpc_common/icon';
    $trigger_attributes->addClass('material-icon');
    $trigger_attributes->addClass('cd-button');
    $trigger_attributes->addClass('cd-button--icon');
    $vars['tag'] = 'button';
    $vars['tag_content'] = $vars['material_icon'];
  }

  $vars['trigger_attributes'] = $trigger_attributes;
  $vars['content_attributes'] = $content_attributes;
}

/**
 * Preprocess an icon.
 *
 * This is implicitely using material icons.
 */
function hpc_common_preprocess_hpc_icon(array &$vars) {
  $vars['#attached']['library'][] = 'hpc_common/icon';
  if (empty($vars['tag'])) {
    $vars['tag'] = 'button';
  }
  $vars['tag_content'] = $vars['icon'];

  $attributes = new Attribute();
  $attributes->addClass('material-icon');
  $attributes->addClass(Html::getClass($vars['icon']));
  if ($vars['tag'] == 'button') {
    $attributes->addClass('cd-button');
    $attributes->addClass('cd-button--icon');
  }
  $vars['attributes'] = $attributes;
}

/**
 * Preprocess a reporting period as text.
 */
function hpc_common_preprocess_hpc_reporting_period(array &$vars) {
  $reporting_period = $vars['reporting_period'];
  if (is_array($reporting_period)) {
    ArrayHelper::sortArrayByNumericKey($reporting_period, 'periodNumber', EndpointQuery::SORT_ASC);
    $first = reset($reporting_period);
    $last = end($reporting_period);
    $start_date = strtotime($first->startDate);
    $end_date = strtotime($last->endDate);
    $period_number = $first != $last ? $first->periodNumber . ' - ' . $last->periodNumber : $first->periodNumber;
  }
  else {
    $start_date = strtotime($reporting_period->startDate);
    $end_date = strtotime($reporting_period->endDate);
    $period_number = $reporting_period->periodNumber;
  }
  $format_string = $vars['format_string'];
  $format_date = 'j M Y';
  $format_date_short = 'j M';

  $start_date_formatted = gmdate($format_date, $start_date);
  $end_date_formatted = gmdate($format_date, $end_date);

  if (gmdate('Y', $start_date) == gmdate('Y', $end_date)) {
    // Same year and month for both start and end date.
    $start_date_formatted = gmdate($format_date_short, $start_date);
  }
  $vars['output'] = new FormattableMarkup(trim($format_string), [
    '@period_number' => $period_number,
    '@date_range' => $start_date_formatted . ' - ' . $end_date_formatted,
  ]);
}

/**
 * Preprocess function for a simple toggle element.
 */
function hpc_common_preprocess_hpc_toggle(array &$vars) {
  $vars['#attached']['library'][] = 'hpc_common/toggle';
  $parent_selector = $vars['parent_selector'];
  $target_selector = $vars['target_selector'];
  $vars['output'] = new FormattableMarkup('<span class="toggle" tabindex="0" data-parent-selector="@parent_selector" data-target-selector="@target_selector"><svg class="cd-icon icon icon--arrow-down" ><use xlink:href="#cd-icon--arrow-down"></use></svg></span>', [
    '@parent_selector' => $parent_selector,
    '@target_selector' => $target_selector,
  ]);
}

/**
 * Preprocess function for a modal link.
 */
function hpc_common_preprocess_hpc_modal_link(array &$vars) {
  if (!empty($vars['tooltip'])) {
    $vars['output'] = [
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $vars['tooltip'],
      '#class' => 'modal-tooltip',
      '#tag_content' => [
        '#theme' => 'hpc_modal_link',
        '#link' => $vars['link'],
      ],
    ];
  }
}
