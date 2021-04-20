<?php

namespace Drupal\ghi_blocks\Plugin\Block;

/**
 * Defines an interface for block plugins that can be synced.
 */
interface SyncableBlockInterface {

  /**
   * Map a configuration object to a new array structure.
   *
   * @param object $config
   *   The source configuration object.
   *
   * @return array
   *   The mapped configuration array.
   */
  public static function mapConfig($config);

}
