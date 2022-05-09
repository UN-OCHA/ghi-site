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
    $render_value = $has_render_context ? $renderer->render($build) : $renderer->renderPlain($build);
    // Re-enable twig debug if it's been enabled before.
    if ($twig_debug) {
      $twig_service->enableDebug();
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
  public static function getThemeOptions($theme_function, $value, array $options) {
    switch ($theme_function) {
      case 'hpc_amount':
        return [
          '#theme' => $theme_function,
          '#amount' => $value,
          '#scale' => !empty($options['scale']) ? $options['scale'] : 'auto',
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
        ];

      case 'hpc_currency':
        return [
          '#theme' => $theme_function,
          '#value' => $value,
          '#scale' => !empty($options['scale']) ? $options['scale'] : 'auto',
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
        ];

      case 'hpc_percent':
        return [
          '#theme' => $theme_function,
          '#percent' => $value,
          '#decimal_format' => !empty($options['decimal_format']) ? $options['decimal_format'] : self::DECIMALS_POINT,
        ];

      case 'hpc_progress_bar':
        return [
          '#theme' => $theme_function,
          '#percent' => $value,
          '#hide_value' => !empty($options['hide_value']) ? $options['hide_value'] : FALSE,
        ];

      default:
        throw new \InvalidArgumentException(sprintf('Unknown theme function "%s"', $theme_function));
    }
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
   * Get the URI to the FTS icon.
   */
  public static function getUriToFtsIcon() {
    return '/' . drupal_get_path('module', 'hpc_common') . '/assets/fts-logo-mobile.png';
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
        'title' => t('View this data in FTS'),
      ],
      '#alt' => t('View this data in FTS'),
    ];
  }

}
