<?php

namespace Drupal\ghi_plans\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sets the configured metric source on selected plans.
 */
#[Action(
  id: 'set_plan_data_source',
  label: new TranslatableMarkup('Set plan data source'),
  type: 'base_object',
)]
class SetPlanDataSource extends SetPlanMetricSourceBase {

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName(): string {
    return 'field_plan_data_source';
  }

}
