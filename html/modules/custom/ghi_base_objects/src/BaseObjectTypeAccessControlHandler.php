<?php

namespace Drupal\ghi_base_objects;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access controller for the Base object type entity.
 *
 * @see \Drupal\ghi_base_objects\Entity\BaseObjectType.
 */
class BaseObjectTypeAccessControlHandler extends EntityAccessControlHandler {

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
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectTypeInterface $entity*/

    // We don't treat the object type label as privileged information, so this
    // check has to be the first one in order to allow labels for all objects
    // to be viewed.
    if ($operation === 'view label') {
      return AccessResult::allowed();
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
