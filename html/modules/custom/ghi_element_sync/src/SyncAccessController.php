<?php

namespace Drupal\ghi_element_sync;

use Drupal\Core\Access\AccessResult;
use Drupal\node\NodeInterface;

/**
 * Access controller for the element sync form.
 */
class SyncAccessController {

  /**
   * Access callback for the element sync form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessElementSyncForm(NodeInterface $node) {
    $allowed = $node->bundle() == 'plan' || ($node->hasField('field_plan') && $node->hasField('field_original_id') && !$node->field_original_id->isEmpty());
    return AccessResult::allowedIf($allowed);
  }

}
