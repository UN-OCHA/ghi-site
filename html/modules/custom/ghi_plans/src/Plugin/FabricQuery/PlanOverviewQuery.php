<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Attachments\CaseloadAttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\FinancialAttachment;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'plan overview' fabric query.
 */
#[FabricQuery(
  id: 'plan_overview',
  label: new TranslatableMarkup('Plan overview query'),
)]
class PlanOverviewQuery extends FabricQueryBase {

  /**
   * The fetched and processed plans.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   */
  private $plans = NULL;

  /**
   * The year to use for the overview data.
   *
   * @var int|null
   */
  private $year = NULL;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery
   */
  private $planQuery = NULL;

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery
   */
  private $attachmentQuery = NULL;

  /**
   * The attachment prototype query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery
   */
  private $attachmentPrototypeQuery = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PlanOverviewQuery {
    /** @var self */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance('plan');
    $instance->attachmentQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance('attachment');
    $instance->attachmentPrototypeQuery = $container->get('plugin.manager.fabric_query_manager')->createInstance('attachment_prototype');
    return $instance;
  }

  /**
   * Public setter for the year.
   *
   * @param int $year
   *   The year.
   */
  public function setYear(int $year): void {
    $this->year = $year;
  }

  /**
   * Public getter for the year.
   *
   * @return int|null
   *   The year.
   */
  public function getYear(): ?int {
    return $this->year;
  }

  /**
   * Retrieve plan data.
   */
  private function retrievePlans(): void {
    // Several homepage blocks ask for plan funding during the same request.
    // Keep the processed response on the query instance after the first call.
    if ($this->plans !== NULL) {
      return;
    }
    $this->plans = [];
    $year = $this->getYear();
    if ($year === NULL) {
      return;
    }

    $plans = $this->planQuery->getPlansByYear($year);
    $plan_ids = $this->extractIds($plans);
    $this->attachmentPrototypeQuery->getDataPrototypesForPlans($plan_ids);

    $attachments = $this->attachmentQuery->getAttachmentsByObject('plan', $plan_ids, ['caseload', 'financial']);
    $caseloads_by_plan = [];
    $requirements_by_plan = [];
    foreach ($attachments as $attachment) {
      if ($attachment instanceof CaseloadAttachmentInterface) {
        $plan_id = $attachment->getPlanId();
        $caseloads_by_plan[$plan_id] = $caseloads_by_plan[$plan_id] ?? [];
        $caseloads_by_plan[$plan_id][$attachment->id()] = $attachment;
      }
      if ($attachment instanceof FinancialAttachment) {
        $plan_id = $attachment->getPlanId();
        $requirements_by_plan[$plan_id] = $requirements_by_plan[$plan_id] ?? [];
        $requirements_by_plan[$plan_id][$attachment->id()] = $attachment->getRequirements();
      }
    }

    foreach ($plans as $plan) {
      $plan_id = $plan->id();
      $plan_object = (object) [
        'plan' => $plan,
        'caseloads' => $caseloads_by_plan[$plan_id] ?? [],
        'requirements' => !empty($requirements_by_plan[$plan_id]) ? reset($requirements_by_plan[$plan_id]) : 0,
      ];
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
  public function getPlans(bool $filter = TRUE): array {
    if ($this->plans === NULL) {
      $this->retrievePlans();
    }
    if ($filter) {
      // Filter by visibility settings.
      $this->filterPlansByVisibilityOnGlobalPages($this->plans);
    }

    uasort($this->plans, function (PlanOverviewPlan $a, PlanOverviewPlan $b) {
      return strnatcmp($a->getName(), $b->getName());
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
  public function getGhoPlans(bool $filter = FALSE): array {
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
   * Get the total requirements for all plans.
   *
   * @return float
   *   The sum of requirements for all GHO plans.
   */
  public function getTotalRequirements(): float {
    $plans = $this->getGhoPlans();
    if (empty($plans)) {
      return 0;
    }
    $requirements = 0;
    foreach ($plans as $plan) {
      $requirements += $plan->getRequirements();
    }
    return $requirements;
  }

  /**
   * Get the number of affected countries for the GHO plans.
   *
   * @return int
   *   The number of unique countries of all GHO plans.
   */
  public function getNumberOfGhoCountries(): int {
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
  public function getCaseloadTotalValues(array $types): array {
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

    foreach ($plans as $plan) {
      $caseload_items = $plan->getPlanCaseloadFields($attachment_overrides[$plan->id()] ?? NULL);
      if (empty($caseload_items)) {
        continue;
      }

      $plan_caseloads[$plan->id()] = [];

      foreach ($types as $type => $label) {
        $value = $plan->getCaseloadValue($type, $label);
        $caseload_totals[$type] += $value ?? 0;
        $plan_caseloads[$plan->id()][$type] = $value;
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
  private function getPlanCaseloadOverridesByPlanId(): array {
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
  private function filterPlansByVisibilityOnGlobalPages(array &$plans): void {
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
      return in_array($plan->id(), $plan_ids);
    });
  }

}
