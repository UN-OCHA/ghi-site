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
   * @param string $element_type
   *   The element type as identified in the remote system.
   * @param bool $dry_run
   *   Optional argument to indicate whether this should be non-changing
   *   operation or not.
   *
   * @return array
   *   The mapped configuration array.
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE);

}
