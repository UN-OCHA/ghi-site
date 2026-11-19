<?php

namespace Drupal\ghi_plans\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sets the prioritized metric source on selected plans.
 */
#[Action(
  id: 'set_plan_prioritized_data_source',
  label: new TranslatableMarkup('Set prioritized metric source'),
  type: 'base_object',
)]
class SetPlanPrioritizedDataSource extends SetPlanMetricSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName(): string {
    return 'field_plan_prioritized_source';
  }

  /**
   * {@inheritdoc}
   */
  protected function isValidSource(string $source): bool {
    return $source === '' || parent::isValidSource($source);
  }

}
