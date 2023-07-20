<?php

namespace Drupal\ghi_subpages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;

/**
 * Controller for autocomplete plan loading.
 */
class LogframeRebuildController extends ControllerBase {

  /**
   * Access callback for the subpages page.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(EntityInterface $entity) {
    if (!$entity instanceof LogframeSubpage) {
      // We allow logframe rebuilds only on logframe nodes.
      return AccessResult::forbidden();
    }
    // Then check if the current user has update rights on the base node.
    return $entity->access('update', NULL, TRUE);
  }

}
