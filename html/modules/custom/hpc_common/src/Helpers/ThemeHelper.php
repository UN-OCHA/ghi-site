<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Utility\Xss;

/**
 * Helper class for theming.
 */
class ThemeHelper {

  /**
   * Define scales for number formatting.
   */
  const SCALE_THOUSAND = 'thousand';
  const SCALE_MILLION = 'million';
  const SCALE_BILLION = 'billion';
  const SCALE_DEFAULT = self::SCALE_MILLION;

  /**
   * Define constants relating to number formating.
   */
  const DECIMALS_POINT = 'point';
  const DECIMALS_COMMA = 'comma';

  /**
   * Define display styles for formatted numeric values.
   */
  const DISPLAY_STYLE_STANDARD = 'standard';
  const DISPLAY_STYLE_COMPACT = 'compact';
  const DISPLAY_STYLE_DEFAULT = self::DISPLAY_STYLE_COMPACT;

  /**
   * Simple wrapper around the render layer.
   */
  public static function theme($theme_key, $options, $cast_to_string = TRUE, $xss_filter = TRUE) {
    $build = [
      '#theme' => $theme_key,
    ] + $options;
    return $cast_to_string ? self::render($build, $xss_filter) : $build;
  }

  /**
   * Wrapper around the render layer.
   *
   * This also disables twig debug if it's enabled, so that the final render
   * string doesn't contain any additional debugging information.
   */
  public static function render($build, $xss_filter = TRUE) {
    // Disable twig debug if it's enabled.
    /** @var \Twig\Environment $twig_service */
    $twig_service = \Drupal::service('twig');
    $twig_debug = $twig_service->isDebug();
    if ($twig_debug) {
      $twig_service->disableDebug();
    }
    // Render the build array using the renderer service.
    $renderer = \Drupal::service('renderer');
    $has_render_context = $renderer->hasRenderContext();
    $render_value = $has_render_context ? $renderer->render($build) : $renderer->renderInIsolation($build);
    // Re-enable twig debug if it's been enabled before.
    if ($twig_debug) {
      $twig_service->enableDebug();
    }
    if ($render_value === NULL) {
      return $render_value;
    }
    return $xss_filter ? trim(Xss::filter($render_value)) : trim($render_value);
  }

  /**
   * Get theme options for a selected list of theme functions.
   *
   * @param string $theme_function
   *   The theme function.
   * @param mixed $value
   *   A value that is passed as the primary renderable value to the function.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   An array of final theme options.
   */
  public static function getThemeOptions($theme_function, $value, array $options = []) {
    switch ($theme_function) {
      case 'hpc_amount':
        $theme_options = [
          '#theme' => $theme_function,
          '#amount' => $value,
          '#scale' => !empty($options['scale']) ? $options['scale'] : 'auto',
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
          // If not specified, use the defaults from hpc_common_theme().
          '#decimals' => !empty($options['decimals']) ? $options['decimals'] : 0,
        ];
        break;

      case 'hpc_currency':
        $theme_options = [
          '#theme' => $theme_function,
          '#value' => $value,
          '#scale' => !empty($options['scale']) ? $options['scale'] : 'auto',
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
          // If not specified, use the defaults from hpc_common_theme().
          '#decimals' => !empty($options['decimals']) ? $options['decimals'] : NULL,
        ];
        break;

      case 'hpc_percent':
        $theme_options = [
          '#theme' => $theme_function,
          '#percent' => $value,
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
        ];
        if (!empty($options['compact_precision'])) {
          $theme_options['#compact_precision'] = TRUE;
        }
        break;

      case 'hpc_progress_bar':
        $theme_options = [
          '#theme' => $theme_function,
          '#percent' => $value,
          '#hide_value' => !empty($options['hide_value']) ? $options['hide_value'] : FALSE,
        ];
        break;

      default:
        throw new \InvalidArgumentException(sprintf('Unknown theme function "%s"', $theme_function));
    }

    if (!empty($options['table_cell'])) {
      $theme_options['#table_cell'] = TRUE;
    }
    return $theme_options;
  }

  /**
   * Apply table number formatting to nested numeric render arrays.
   *
   * Configuration items can wrap their numeric value in containers, links,
   * and tooltips, so the marker must be applied recursively.
   *
   * @param array $build
   *   A render array or nested table-cell structure.
   *
   * @return array
   *   The structure with numeric render arrays marked as table-cell output.
   */
  public static function applyTableNumberFormatting(array $build): array {
    $numeric_themes = [
      'hpc_amount',
      'hpc_autoformat_value',
      'hpc_currency',
      'hpc_percent',
      'hpc_pie_chart',
      'hpc_progress_bar',
    ];
    if (isset($build['#theme']) && in_array($build['#theme'], $numeric_themes, TRUE)) {
      $build['#table_cell'] = TRUE;
    }

    foreach ($build as $key => $value) {
      if (is_array($value)) {
        $build[$key] = self::applyTableNumberFormatting($value);
      }
    }
    return $build;
  }

  /**
   * Get a suffix for numeric data based on the scale of the figure.
   */
  public static function getNumberSuffix($scale, $abbreviation = TRUE) {
    switch ($scale) {
      case self::SCALE_THOUSAND:
        $suffix = $abbreviation ? 'k' : ' ' . t('thousand');
        break;

      case self::SCALE_MILLION:
        $suffix = $abbreviation ? 'm' : ' ' . t('million');
        break;

      case self::SCALE_BILLION:
        $suffix = $abbreviation ? 'bn' : ' ' . t('billion');
        break;

      default:
        $suffix = '';
        break;
    }

    return $suffix;
  }

  /**
   * Get the available numeric display styles.
   *
   * @return array
   *   The display style options.
   */
  public static function getDisplayStyleOptions(): array {
    return [
      self::DISPLAY_STYLE_STANDARD => t('Standard'),
      self::DISPLAY_STYLE_COMPACT => t('Compact'),
    ];
  }

  /**
   * Get the globally configured numeric display style.
   *
   * @return string
   *   The configured display style.
   */
  public static function getDisplayStyle(): string {
    if (!\Drupal::hasService('config.factory')) {
      return self::DISPLAY_STYLE_DEFAULT;
    }

    $display_style = \Drupal::config('hpc_common.settings')->get('numeric_display_style');
    return in_array($display_style, [self::DISPLAY_STYLE_STANDARD, self::DISPLAY_STYLE_COMPACT], TRUE) ? $display_style : self::DISPLAY_STYLE_DEFAULT;
  }

  /**
   * Check whether the global numeric display style is compact.
   *
   * @return bool
   *   TRUE when compact formatting should be used.
   */
  public static function isCompactDisplayStyle(): bool {
    return self::getDisplayStyle() === self::DISPLAY_STYLE_COMPACT;
  }

  /**
   * Get the suffix used by the compact display style.
   *
   * @param string $scale
   *   The numeric scale.
   *
   * @return string
   *   The compact suffix.
   */
  public static function getCompactNumberSuffix(string $scale): string {
    return match ($scale) {
      self::SCALE_THOUSAND => 'k',
      self::SCALE_MILLION => 'M',
      self::SCALE_BILLION => 'B',
      default => '',
    };
  }

  /**
   * Get the URI to the FTS icon.
   */
  public static function getUriToFtsIcon() {
    /** @var \Drupal\Core\Extension\ExtensionPathResolver $path_resolver */
    $path_resolver = \Drupal::service('extension.path.resolver');
    return '/' . $path_resolver->getPath('module', 'hpc_common') . '/assets/fts-logo-mobile.png';
  }

  /**
   * Get a render array for the FTS icon.
   */
  public static function themeFtsIcon() {
    return [
      '#theme' => 'image',
      '#uri' => self::getUriToFtsIcon(),
      '#attributes' => [
        'class' => 'fts-icon',
      ],
    ];
  }

}
