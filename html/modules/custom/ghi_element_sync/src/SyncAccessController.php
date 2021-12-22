<?php

namespace Drupal\ghi_element_sync;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\node\NodeInterface;

/**
 * Access controller for the element sync form.
 */
class SyncAccessController extends ControllerBase {

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
    $config = $this->config('ghi_element_sync.settings');
    if (empty($config->get('sync_source'))) {
      return AccessResult::forbidden();
    }
    if (!in_array($node->bundle(), $config->get('node_types') ?? [])) {
      return AccessResult::forbidden();
    }
    $base_object = BaseObjectHelper::getBaseObjectFromNode($node);
    return AccessResult::allowedIf($base_object && $base_object->bundle() == 'plan');
  }

}
