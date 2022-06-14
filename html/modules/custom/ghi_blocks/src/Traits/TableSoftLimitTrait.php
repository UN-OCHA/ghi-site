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
   *
   * @return array
   *   A form array.
   */
  public function buildSoftLimitFormElement($default_value) {
    return [
      '#type' => 'number',
      '#title' => $this->t('Soft limit'),
      '#description' => $this->t('Leave empty to not apply a limit'),
      '#min' => 5,
      '#default_value' => $default_value,
    ];
  }

}
