<?php

namespace Drupal\ghi_plans\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\ghi_plans\Entity\Plan;

/**
 * Provides shared validation and access for plan metric source actions.
 */
abstract class SetPlanMetricSourceBase extends ActionBase {

  /**
   * The permission required to use plan metric source actions.
   */
  public const PERMISSION = 'manage plan metric source';

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    if (!array_key_exists('source', $this->configuration)) {
      return;
    }
    $source = $this->configuration['source'];
    if (!is_string($source) || !$entity instanceof Plan || !$entity->hasField($this->getSourceFieldName()) || !$this->isValidSource($source)) {
      return;
    }
    $entity->set($this->getSourceFieldName(), $source === '' ? NULL : $source);
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $allowed = $object instanceof Plan && $object->hasField($this->getSourceFieldName()) && $account?->hasPermission(self::PERMISSION);
    $access = AccessResult::allowedIf($allowed);
    return $return_as_object ? $access : $access->isAllowed();
  }

  /**
   * Gets the plan field changed by the action.
   */
  abstract protected function getSourceFieldName(): string;

  /**
   * Checks whether the configured source is valid for the target field.
   */
  protected function isValidSource(string $source): bool {
    return Plan::isValidDataSource($source);
  }

}
