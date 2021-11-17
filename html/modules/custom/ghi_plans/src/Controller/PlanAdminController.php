<?php

namespace Drupal\ghi_plans\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Controller for admin features on plans.
 */
class PlanAdminController extends ControllerBase {

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
    return AccessResult::allowedIf($node->bundle() == 'plan');
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
  public function planSettingsTitle(NodeInterface $node) {
    return $this->t('Plan settings for %label', ['%label' => $node->label()]);
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
  public function planStructureTitle(NodeInterface $node) {
    return $this->t('Plan structure for %label', ['%label' => $node->label()]);
  }

}
