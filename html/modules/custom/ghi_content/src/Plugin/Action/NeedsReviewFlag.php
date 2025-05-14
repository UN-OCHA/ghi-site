<?php

namespace Drupal\ghi_content\Plugin\Action;

/**
 * Custom action to set the needs review flag on a node.
 *
 * @Action(
 *   id = "needs_review_flag",
 *   label = @Translation("Mark as 'needs review'"),
 *   type = "node"
 * )
 */
class NeedsReviewFlag extends NeedsReviewActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($node = NULL) {
    $this->doExecute($node, TRUE);
  }

}
