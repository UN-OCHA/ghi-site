<?php

namespace Drupal\ghi_blocks\Helpers;

use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;

/**
 * Helper function for global settings.
 *
 * @see \Drupal\ghi_blocks\Traits\GlobalSettingsTrait
 */
class GlobalSettingsHelper {

  use GlobalSettingsTrait;

  /**
   * Provides config access for GlobalSettingsTrait outside a form or block.
   *
   * @param string $name
   *   The configuration object name.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   The configuration object.
   */
  protected function config($name) {
    return \Drupal::config($name);
  }

  /**
   * Get the global config for a specific year.
   *
   * @param int $year
   *   The year for which to fetch the global config.
   *
   * @return array
   *   An array with the global config for the given year.
   */
  public static function getConfig($year) {
    $config_key = self::getConfigKey();
    $config = \Drupal::config($config_key)->get($year);
    return $config;
  }

}
