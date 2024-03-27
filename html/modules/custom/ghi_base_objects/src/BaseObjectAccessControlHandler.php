<?php

namespace Drupal\ghi_base_objects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Base object entity.
 *
 * @see \Drupal\ghi_base_objects\Entity\BaseObject.
 */
class BaseObjectAccessControlHandler extends EntityAccessControlHandler {

  /**
   * Allow access to user label.
   *
   * @var bool
   */
  protected $viewLabelOperation = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $entity*/

    // We don't treat the object label as privileged information, so this check
    // has to be the first one in order to allow labels for all objects to be
    // viewed.
    if ($operation === 'view label') {
      return AccessResult::allowed();
    }

    if ($operation == 'view') {
      return AccessResult::forbidden();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
