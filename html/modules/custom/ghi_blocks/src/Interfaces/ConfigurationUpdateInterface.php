<?php

namespace Drupal\ghi_blocks\Interfaces;

/**
 * Defines a block plugin that can migrate its saved configuration.
 */
interface ConfigurationUpdateInterface {

  /**
   * Updates the plugin configuration in memory.
   *
   * @return bool
   *   TRUE when the configuration changed, otherwise FALSE.
   */
  public function updateConfiguration(): bool;

}
