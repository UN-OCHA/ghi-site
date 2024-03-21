<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Common helpers for blocks using config validation.
 */
trait ConfigValidationTrait {

  /**
   * {@inheritdoc}
   */
  public function validateConfiguration() {
    return empty($this->getConfigErrors());
  }

}
