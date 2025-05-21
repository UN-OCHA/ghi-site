<?php

namespace Drupal\ghi_content\Plugin\Action;

/**
 * Custom action to unset the needs review flag on a node.
 *
 * @Action(
 *   id = "needs_review_unflag",
 *   label = @Translation("Mark as 'reviewed'"),
 *   type = "node"
 * )
 */
class NeedsReviewUnflag extends NeedsReviewActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    $this->doExecute($node, FALSE);
  }

}
