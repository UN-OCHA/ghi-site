<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a query plugin for plan overview data.
 *
 * @EndpointQuery(
 *   id = "plan_overview_query",
 *   label = @Translation("Plan overview query"),
 *   endpoint = {
 *     "public" = "public/plan/overview/{year}",
 *     "authenticated" = "plan/overview/{year}",
 *     "version" = "v2"
 *   }
 * )
 */
class PlanOverviewQuery extends EndpointQueryBase {

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

    $plan_objects = $data->plans;
    foreach ($plan_objects as $plan_object) {
      $plan = new PlanOverviewPlan($plan_object);
      $this->plans[$plan->id()] = $plan;
    }
  }

  /**
   * Get plans.
   *
   * @param bool $filter
   *   Whether the plans should be filtered or not.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   An array of plan overview plan objects keyed by plan id.
   */
  public function getPlans($filter = TRUE) {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    if ($filter) {
      // Filter by visibility settings.
      $this->filterPlansByVisibilityOnGlobalPages($this->plans);
    }

    uasort($this->plans, function ($a, $b) {
      return strnatcmp($a->name, $b->name);
    });
    return $this->plans;
  }

  /**
   * Get the GHO plans.
   *
   * @param bool $filter
   *   Whether the plans should be filtered or not.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   An array of GHO plans.
   */
  public function getGhoPlans($filter = FALSE) {
    $plans = $this->getPlans($filter);
    if (empty($plans)) {
      return [];
    }
    $plans = array_filter($plans, function (PlanOverviewPlan $plan) {
      return $plan->isPartOfGho();
    });
    return $plans;
  }

  /**
   * Get the number of affected countries for the GHO plans.
   *
   * @return int
   *   The number of unique countries of all GHO plans.
   */
  public function getNumerOfGhoCountries() {
    // Get the GHO plans, but make sure they are not filtered for visibility.
    // The number of affected countries will appear only in the key figures
    // element, where we want the number of countries for all GHO plans
    // independently of whether specific plans are hidden from global pages or
    // not.
    $plans = $this->getGhoPlans(FALSE);
    if (empty($plans)) {
      return 0;
    }

    $countries = [];
    foreach ($plans as $plan) {
      $plan_countries = $plan->getCountries();
      if (empty($plan_countries)) {
        continue;
      }
      foreach ($plan_countries as $plan_country) {
        if (array_key_exists($plan_country->id(), $countries)) {
          continue;
        }
        $countries[$plan_country->id()] = $plan_country;
      }
    }

    return count($countries);
  }

  /**
   * Get the caseload total values for the supplied types.
   *
   * @param array $types
   *   The types of caseload of which the sum is to be returned. The keys
   *   should be the expected metric type, the values the metric label.
   *
   * @return array
   *   An array keyed by the type and valued by the total sum of that type
   */
  public function getCaseloadTotalValues(array $types) {
    // Get the GHO plans, but make sure they are not filtered for visibility.
    // The caseload totals will appear only in the key figures element, where
    // we want the full GHO figures independently of whether specific plans are
    // hidden from global pages or not.
    $plans = $this->getGhoPlans(FALSE);

    // Setting up the array keyed by the types and values as 0.
    $caseload_totals = array_fill_keys(array_keys($types), 0);
    $plan_caseloads = [];
    $caseload_totals['target_custom'] = 0;
    $caseload_totals['reached_custom'] = 0;

    // Load the override settings per plan.
    $attachment_overrides = $this->getPlanCaseloadOverridesByPlanId();

    // Since all plans are now populated with people in need and target values,
    // the total GHO people in need and people targeted can be calculated by
    // summing these plans caseload values for all plans that have
    // isPartOfGho = true from this endpoint:
    // https://api.hpc.tools/v2/plan/overview/{year}
    foreach ($plans as $plan) {

      // Check caseLoads and respective totals property has value.
      $caseload_items = $plan->getPlanCaseloadFields($attachment_overrides[$plan->id()] ?? NULL);
      if (empty($caseload_items)) {
        continue;
      }

      $plan_caseloads[$plan->id()] = [];

      foreach ($types as $type_key => $type) {
        $label = is_scalar($type) ? $type : ($type['label'] ?? NULL);
        $key = is_array($type) && !empty($type['type']) ? $type['type'] : $type_key;
        $fallback = is_array($type) && !empty($type['fallback']) ? $type['fallback'] : NULL;
        $value = $plan->getCaseloadValue($key, $label, $fallback);
        $caseload_totals[$type_key] += $value ?? 0;
        $plan_caseloads[$plan->id()][$type_key] = $value;
      }
    }

    // Calculate custom target and reached values, to be used for percentage
    // calculations in KeyFigures::getData(), based on additional business
    // logic.
    foreach ($plan_caseloads as $plan_id => $caseload) {
      $plan = $plans[$plan_id];
      if ($plan->isRrp()) {
        continue;
      }
      if (empty($caseload['target']) || empty($caseload['reached'])) {
        // Only add target and reached to the totals for percentage if both are
        // non-NULL.
        continue;
      }
      $caseload_totals['target_custom'] += $caseload['target'];
      // If reached is higher than target, use target instead, so that the
      // final percentage per plan can't get over 100%.
      $caseload_totals['reached_custom'] += min($caseload['reached'], $caseload['target']);
    }

    return $caseload_totals;
  }

  /**
   * Get specific plan caseload overrides keyed by plan id.
   *
   * Per plan base object, a specific caseload can be specified in the backend,
   * which should be used whenever data from the plan level caseload should be
   * shown. Here we load them in one go to have them easily available.
   *
   * @return array
   *   An array with the attachment ids of specific plan level caseload
   *   attachments, keyed by the plan id.
   */
  private function getPlanCaseloadOverridesByPlanId() {
    $plans = $this->getPlans();
    $caseload_overrides = [];
    if (empty($plans)) {
      return $caseload_overrides;
    }
    $result = \Drupal::entityTypeManager()
      ->getStorage('base_object')
      ->loadByProperties([
        'type' => 'plan',
        'field_original_id' => array_keys($plans),
      ]);
    if (empty($result)) {
      return $caseload_overrides;
    }
    foreach ($result as $plan) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan */
      $attachment_id = $plan->field_plan_caseload->attachment_id;
      $caseload_overrides[$plan->getSourceId()] = $attachment_id !== NULL ? (int) $attachment_id : NULL;
    }
    return array_filter($caseload_overrides);
  }

  /**
   * Filter the given list of plans by global visibility settings.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   An array of plan objects.
   */
  private function filterPlansByVisibilityOnGlobalPages(array &$plans) {
    if (empty($plans)) {
      return;
    }
    $result = \Drupal::entityTypeManager()
      ->getStorage('base_object')
      ->loadByProperties([
        'type' => 'plan',
        'field_original_id' => array_keys($plans),
        'field_visible_on_global_pages' => 1,
      ]);
    if (empty($result)) {
      $plans = [];
      return;
    }
    $plan_ids = array_map(function ($plan_entity) {
      /** @var \Drupal\ghi_plans\Entity\Plan $plan_entity */
      return $plan_entity->getSourceId();
    }, $result);
    $plans = array_filter($plans, function ($plan) use ($plan_ids) {
      return in_array($plan->id, $plan_ids);
    });
  }

}
