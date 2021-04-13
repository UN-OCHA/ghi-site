<?php

namespace Drupal\hpc_common\Helpers;

use Drupal\Component\Utility\Xss;

/**
 * Helper class for theming.
 */
class ThemeHelper {

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
   * Simple wrapper around the render layer.
   */
  public static function render($build, $xss_filter = TRUE) {
    $renderer = \Drupal::service('renderer');
    $has_render_context = $renderer->hasRenderContext();
    $render_value = $has_render_context ? $renderer->render($build) : $renderer->renderPlain($build);
    return $xss_filter ? trim(Xss::filter($render_value)) : trim($render_value);
  }

}
