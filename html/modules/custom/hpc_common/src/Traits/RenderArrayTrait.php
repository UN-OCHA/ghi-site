<?php

namespace Drupal\hpc_common\Traits;

use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Trait providing shorthand versions for create render arrays.
 */
trait RenderArrayTrait {

  /**
   * Build a render array for a theme function.
   *
   * @param string $theme_function
   *   The theme function.
   * @param mixed $value
   *   A value that is passed as the primary renderable value to the function.
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   An render array.
   */
  public function buildRenderArray($theme_function, $value, array $options = []) {
    return ThemeHelper::getThemeOptions($theme_function, $value, $options);
  }

}
