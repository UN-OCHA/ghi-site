<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a query plugin for plan overview data.
 *
 * @EndpointQuery(
 *   id = "funding_overview_query",
 *   label = @Translation("Plan funding overview query"),
 *   endpoint = {
 *     "public" = "public/plan/overview/{year}",
 *     "version" = "v2"
 *   }
 * )
 */
class FundingOverviewQuery extends EndpointQueryBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The fetched and processed plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   */
  private $plans = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->moduleHandler = $container->get('module_handler');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $this->endpointQuery->setPlaceholders($placeholders);
    $year = $this->getPlaceholder('year');
    if (!$year) {
      return;
    }
    $this->moduleHandler->alter('plan_overview_query_arguments', $query_args, $year);
    return parent::getData($placeholders, $query_args);
  }

  /**
   * Retrieve plan data.
   */
  private function retrievePlans() {
    $this->plans = [];
    $query_args = [];

    $data = $this->getData([], $query_args);
    if (empty($data) || empty($data->plans)) {
      return;
    }
    foreach ($data->plans as $plan) {
      $this->plans[$plan->id] = $plan;
    }
  }

  /**
   * Get the funding by plan for all plans of the overview year.
   *
   * @return array
   *   The funding per plan as a simple array.
   */
  public function getFundingByPlans() {
    $this->retrievePlans();

    $funding = [];
    foreach ($this->plans as $plan_id => $plan) {
      $funding[$plan_id] = $plan->funding?->totalFunding ?? NULL;
    }
    return $funding;
  }

}
