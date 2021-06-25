<?php

namespace Drupal\ghi_element_sync;

use Drupal\node\NodeInterface;

/**
 * Defines an interface for block plugins that can be synced.
 */
interface SyncableBlockInterface {

  /**
   * Map a configuration object to a new array structure.
   *
   * @param object $config
   *   The source configuration object.
   * @param \Drupal\node\NodeInterface $node
   *   The node to be synced.
   *
   * @return array
   *   The mapped configuration array.
   */
  public static function mapConfig($config, NodeInterface $node);

}
