<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\node\NodeInterface;

/**
 * Access controller for the element sync form.
 */
class SyncAccessController {

  use LayoutEntityHelperTrait;

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
    $allowed = $node->bundle() == 'plan' || ($node->hasField('field_plan') && $node->hasField('field_original_id') && !empty($node->field_original_id->isEmpty()));
    return AccessResult::allowedIf($allowed);
  }

}
