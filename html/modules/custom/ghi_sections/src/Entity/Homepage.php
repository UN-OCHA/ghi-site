<?php

namespace Drupal\ghi_sections\Entity;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Session\AccountInterface;

/**
 * Bundle class for homepage section nodes.
 */
class Homepage extends GlobalSection implements SectionNodeInterface, ImageNodeInterface {

  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $account ?? \Drupal::currentUser();
    if ($operation == 'view' && $account->isAnonymous()) {
      return $return_as_object ? new AccessResultForbidden() : FALSE;
    }
    return parent::access($operation, $account, $return_as_object);
  }

}
