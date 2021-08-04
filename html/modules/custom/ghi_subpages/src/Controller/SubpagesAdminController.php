<?php

namespace Drupal\ghi_subpages\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ghi_subpages\Helpers\SubpageHelper;
use Drupal\node\NodeInterface;

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
   * The _title_callback for the page that renders the admin form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The current node.
   *
   * @return string
   *   The page title.
   */
  public function title(NodeInterface $node) {
    return $this->t('Subpages for %label', ['%label' => $node->label()]);
  }

}
