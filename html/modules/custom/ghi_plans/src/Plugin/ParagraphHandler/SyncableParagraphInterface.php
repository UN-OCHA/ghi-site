<?php

namespace Drupal\ghi_plans\Plugin\ParagraphHandler;

/**
 * Defines an interface for paragraph handler plugins that can be synced.
 */
interface SyncableParagraphInterface {

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

  /**
   * Get element key of the source element.
   *
   * @return string
   *   The string id of the source element as defined in the source site.
   */
  public static function getSourceElementKey();

}
