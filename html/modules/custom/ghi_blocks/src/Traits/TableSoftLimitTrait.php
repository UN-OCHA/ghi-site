<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Helper trait for tables with soft limits.
 */
trait TableSoftLimitTrait {

  /**
   * Build the form element for the configuration of the soft limit.
   *
   * @param int $default_value
   *   The default value to set.
   * @param int $min
   *   The minimum value to set.
   * @param int $max
   *   The maximum value to set.
   *
   * @return array
   *   A form array.
   */
  public function buildSoftLimitFormElement($default_value, $min = 5, $max = NULL) {
    return [
      '#type' => 'number',
      '#title' => $this->t('Soft limit'),
      '#description' => $this->t('Leave empty to not apply a limit'),
      '#min' => $min,
      '#max' => $max,
      '#default_value' => $default_value,
    ];
  }

}
