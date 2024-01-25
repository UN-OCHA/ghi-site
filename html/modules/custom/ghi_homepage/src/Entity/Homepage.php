<?php

namespace Drupal\ghi_homepage\Entity;

use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Bundle class for homepage container nodes.
 */
class Homepage extends Node {

  const BUNDLE = 'homepage';

  /**
   * Get the year associated to the homepage.
   *
   * @return int
   *   The year.
   */
  public function getYear() {
    return $this->get('field_year')->value;
  }

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
