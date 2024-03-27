<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface class for blocks supporting config validation.
 */
interface ConfigValidationInterface {

  /**
   * Validate the configuration of the block.
   *
   * @return bool
   *   TRUE if the configuration is valid, FALSE otherwise.
   */
  public function validateConfiguration();

  /**
   * Get a list of configuration errors.
   *
   * @return array
   *   An array of configuration errors.
   */
  public function getConfigErrors();

  /**
   * Attempt to fix the config errors.
   */
  public function fixConfigErrors();

}
