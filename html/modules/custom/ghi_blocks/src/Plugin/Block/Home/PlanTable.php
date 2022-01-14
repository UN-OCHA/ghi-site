<?php

namespace Drupal\ghi_blocks\Plugin\Block\Home;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;

/**
 * Provides a 'PlanTable' block.
 *
 * @Block(
 *  id = "home_plan_table",
 *  admin_label = @Translation("Plan table"),
 *  category = @Translation("Homepage"),
 *  data_sources = {
 *    "plans" = "plan_overview_query",
 *  },
 *  context_definitions = {
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class PlanTable extends GHIBlockBase {

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $plans = $this->getPlans();

    $header = [
      $this->t('Interagency Response Plans'),
    ];

    foreach ($plans as $plan) {
      $rows[] = [
        'name' => $plan['name'],
      ];
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationDefaults() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    return $form;
  }

  /**
   * Retrieve the plans to display in this block.
   *
   * @return array
   *   Array of plan items.
   */
  private function getPlans() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanOverviewQuery $query */
    $query = $this->getQueryHandler('plans');
    return $query->getPlans();
  }

}
