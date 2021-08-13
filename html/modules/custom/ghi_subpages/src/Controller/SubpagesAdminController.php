<?php

namespace Drupal\ghi_subpages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * Controller for autocomplete plan loading.
 */
class SubpagesAdminController extends ControllerBase {

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node) {
    return AccessResult::allowedIf(in_array($node->bundle(), SubpageHelper::SUPPORTED_BASE_TYPES));
  }

  /**
   * Access callback for the plan structure page.
   *
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The node type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function nodeCreateAccess(NodeTypeInterface $node_type) {
    if (in_array($node_type->id(), SubpageHelper::SUPPORTED_SUBPAGE_TYPES)) {
      // We don't want subpages to be created manually, as this process is
      // automatic whenever a base page is created.
      return AccessResult::forbidden();
    }
    // Fall back to node type access check.
    return $node_type->access('create', NULL, TRUE);
  }

  /**
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Subpages for @type %label', [
      '@type' => $node->type->entity->label(),
      '%label' => $node->label(),
    ]);
  }

}
