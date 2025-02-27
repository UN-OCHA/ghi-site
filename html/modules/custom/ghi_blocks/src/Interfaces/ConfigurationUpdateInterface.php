<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Interface for blocks that handle their own configuration updates.
 */
interface ConfigurationUpdateInterface {

  /**
   * Update the configuration.
   */
  public function updateConfiguration();

}
