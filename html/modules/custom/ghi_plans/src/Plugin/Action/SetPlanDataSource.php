<?php

namespace Drupal\ghi_plans\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Sets the configured metric source on selected plans.
 */
#[Action(
  id: 'set_plan_data_source',
  label: new TranslatableMarkup('Set plan data source'),
  type: 'base_object',
)]
class SetPlanDataSource extends ActionBase {

  /**
   * The permission required to use this action.
   */
  public const PERMISSION = 'manage plan metric source';

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    $source = $this->configuration['source'] ?? NULL;
    if (!$entity instanceof Plan || !$entity->hasField('field_plan_data_source') || !Plan::isValidDataSource($source)) {
      return;
    }
    $entity->set('field_plan_data_source', $source);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $allowed = $object instanceof Plan && $object->hasField('field_plan_data_source') && $account?->hasPermission(self::PERMISSION);
    $access = AccessResult::allowedIf($allowed);
    return $return_as_object ? $access : $access->isAllowed();
  }

}
