<?php

namespace Drupal\ghi_plans\Plugin\views\field;

use Drupal\ghi_plans\Entity\Plan;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Displays the effective prioritized metric source for a plan.
 */
#[ViewsField('plan_prioritized_source')]
class PlanPrioritizedSource extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // The value is derived from the result entity and needs no query field.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $plan = $this->getEntity($values);
    if (!$plan instanceof Plan) {
      return '';
    }
    $source_options = Plan::getDataSourceOptions();
    $label = $source_options[$plan->getEffectivePrioritizedDataSource()];
    return $plan->getPrioritizedDataSourceOverride() === NULL ? $this->t('@source (inherited)', ['@source' => $label]) : $label;
  }

}
