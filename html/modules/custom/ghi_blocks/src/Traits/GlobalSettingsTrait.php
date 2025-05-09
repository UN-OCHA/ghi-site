<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\ghi_plans\ApiObjects\Mocks\PlanOverviewPlanMock;
use Drupal\ghi_plans\Traits\PlanTypeTrait;
use Drupal\ghi_sections\SectionManager;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Trait for global settings.
 *
 * Used in the global config form and can be used in any block plugin that can
 * show on global pages.
 */
trait GlobalSettingsTrait {

  use PlanTypeTrait;

  /**
   * Get the config key to be used for the global year settings.
   *
   * @return string
   *   The config key.
   */
  public static function getConfigKey() {
    return 'ghi_blocks.global_settings';
  }

  /**
   * Get the section manager.
   *
   * @return \Drupal\ghi_sections\SectionManager
   *   The section manager instance.
   */
  public function getSectionManager() {
    if (property_exists($this, 'sectionManager') && $this->sectionManager instanceof SectionManager) {
      return $this->sectionManager;
    }
    return \Drupal::service('ghi_sections.manager');
  }

  /**
   * Get the global year config settings.
   *
   * @param int $year
   *   The year for which to retrieve the settings.
   *
   * @return array|null
   *   The config settings if already available.
   */
  public function getYearConfig($year) {
    $config_key = $this->getConfigKey();
    /** @var \Drupal\Core\Config\Config|\Drupal\Core\Config\ImmutableConfig $config */
    $config = $this->config($config_key);
    return $config ? $config->get($year) : NULL;
  }

  /**
   * Apply the global configuration to an array of plan objects.
   *
   * This requires that the table data is using associative arrays for both
   * header and rows.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   The plans.
   * @param int $year
   *   The year for which the configuration should be applied.
   */
  public function applyGlobalConfigurationPlans(array &$plans, $year) {
    $config = $this->getYearConfig($year);

    // Sort by plan type.
    if (!empty($config['sort_by_plan_type'])) {
      $this->sortPlansByPlanType($plans, $config['plan_short_names'] ?? FALSE);

      // Put the plans together, additionally grouped by them being included in
      // the GHO, with GHO plans coming first.
      $plans_gho = [];
      $plans_non_gho = [];
      foreach ($plans as $plan) {
        /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
        if ($plan->isPartOfGho()) {
          $plans_gho[$plan->id()] = $plan;
        }
        else {
          $plans_non_gho[$plan->id()] = $plan;
        }
      }
      $plans = $plans_gho + $plans_non_gho;
    }
    else {
      // Otherwise sort by plan name only.
      ArrayHelper::sortObjectsByProperty($plans, 'getName', EndpointQuery::SORT_ASC, SORT_STRING);
    }
  }

  /**
   * Apply the global configuration to a table.
   *
   * This requires that the table data is using associative arrays for both
   * header and rows.
   *
   * @param array $header
   *   The build header array.
   * @param array $rows
   *   The build table rows.
   * @param array $cache_tags
   *   The cache tags array that will be filled with the cache tags of existing
   *   section nodes.
   * @param int $year
   *   The year for which the configuration should be applied.
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   An array of plan objects.
   */
  private function applyGlobalConfigurationTable(array &$header, array &$rows, array &$cache_tags, $year, array $plans) {
    $config = $this->getYearConfig($year);

    // First make sure specific columns are using the array notation so that we
    // have common ground to work on.
    $columns = ['name', 'type'];
    $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($columns) {
      foreach ($columns as $column) {
        if (!is_array($row[$column])) {
          $row[$column] = ['data' => ['name' => ['#markup' => $row[$column]]]];
        }
        $row[$column]['data'] += [
          'tooltips' => [
            '#theme' => 'hpc_tooltip_wrapper',
            '#tooltips' => [],
          ],
        ];
      }
      return $row;
    }, $rows);

    if (!empty($config['plan_short_names'])) {
      // Replace plan name with short name if available.
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
        /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
        $plan = $plans[$plan_id] ?? NULL;
        if (!$plan || $plan instanceof PlanOverviewPlanMock) {
          return $row;
        }
        $short_name = $plan->getShortName();
        $row['name']['data']['name']['#markup'] = $short_name;
        $row['name']['data-value'] = $short_name;
        $row['name']['data-raw-value'] = $short_name;
        return $row;
      }, $rows);
    }

    // Link to existing sections if possible.
    $section_manager = $this->getSectionManager();
    $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans, $section_manager, &$cache_tags) {
      /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
      $plan = $plans[$plan_id] ?? NULL;
      if (!$plan || !$plan->getEntity()) {
        return $row;
      }
      $section = $section_manager->loadSectionForBaseObject($plan->getEntity());
      if (!$section) {
        return $row;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $section->getCacheTags());
      if (!$section->isPublished()) {
        // Hide document link if section is not published.
        $row['status']['data']['content']['document'] = NULL;
        return $row;
      }
      $row['name']['data']['name'] = $section->toLink($row['name']['data']['name']['#markup'])->toRenderable();
      return $row;
    }, $rows);

    if (!empty($config['plan_included_in_gho_tooltip'])) {
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
        /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
        $plan = $plans[$plan_id] ?? NULL;
        if (!$plan || $plan->isPartOfGho()) {
          return $row;
        }
        $row['name']['data']['tooltips']['#tooltips'][] = [
          '#theme' => 'hpc_tooltip',
          '#tooltip' => $this->t('This plan is not included in the GHO totals'),
          '#class' => 'gho-included-tooltip',
          '#tag_content' => [
            '#theme' => 'hpc_icon',
            '#icon' => 'warning',
            '#tag' => 'span',
          ],
        ];
        return $row;
      }, $rows);
    }

    if (!empty($config['plan_type_icons'])) {
      // Add plan type icons to plan name column.
      unset($header['type']);
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans, $plan_type_short_name) {
        /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
        $plan = $plans[$plan_id] ?? NULL;
        unset($row['type']);
        if (!$plan) {
          return $row;
        }
        $row['name']['data']['tooltips']['#tooltips'][] = [
          '#theme' => 'hpc_tooltip',
          '#tooltip' => $plan->getTypeName(TRUE),
          '#tag' => 'span',
          '#tag_content' => $plan->getTypeShortName(),
          '#class' => [
            'plan-type-icon',
            Html::getClass('plan-type-' . $plan->getTypeShortName()),
          ],
        ];
        return $row;
      }, $rows);
    }

    // Handle optional funding columns.
    if (empty($config['funding'])) {
      // Hide the funding column.
      unset($header['funding']);
      $rows = array_map(function ($row) {
        unset($row['funding']);
        return $row;
      }, $rows);
    }

    // Handle optional requirements columns.
    if (empty($config['requirements'])) {
      // Hide the requirements column.
      unset($header['requirements']);
      $rows = array_map(function ($row) {
        unset($row['requirements']);
        return $row;
      }, $rows);
    }

    // Handle optional coverage columns.
    if (empty($config['coverage'])) {
      // Hide the coverage column.
      unset($header['coverage']);
      $rows = array_map(function ($row) {
        unset($row['coverage']);
        return $row;
      }, $rows);
    }

    // Handle optional caseload columns.
    if (empty($config['caseload_expected_reach'])) {
      // Hide the expected reach column.
      unset($header['expected_reach']);
      $rows = array_map(function ($row) {
        unset($row['expected_reach']);
        return $row;
      }, $rows);
    }

    // Handle optional caseload columns.
    if (empty($config['caseload_expected_reached'])) {
      // Hide the expected reach column.
      unset($header['expected_reached']);
      $rows = array_map(function ($row) {
        unset($row['expected_reached']);
        return $row;
      }, $rows);
    }

    if (empty($config['caseload_latest_reach'])) {
      // Hide the reached column.
      unset($header['latest_reach']);
      $rows = array_map(function ($row) {
        unset($row['latest_reach']);
        return $row;
      }, $rows);
    }

    if (empty($config['caseload_reached'])) {
      // Hide the reached column.
      unset($header['reached']);
      $rows = array_map(function ($row) {
        unset($row['reached']);
        return $row;
      }, $rows);
    }

    // Plan status and document downloads are handled together.
    if (empty($config['plan_status'])) {
      // Hide the plan status column.
      $rows = array_map(function ($row) {
        unset($row['status']['data']['content']['plan_status']);
        return $row;
      }, $rows);
    }
    if (empty($config['plan_downloads'])) {
      // Hide the document downloads column.
      $rows = array_map(function ($row) {
        unset($row['status']['data']['content']['document']);
        if (array_key_exists('document', $row)) {
          // This is set if the table is downloaded.
          unset($row['document']);
        }
        return $row;
      }, $rows);
    }
    $status_items = array_filter(array_map(function ($row) {
      $columns = array_filter($row['status']['data']['content'] ?? []);
      return empty($columns) ? NULL : $columns;
    }, $rows));
    if (empty($status_items)) {
      unset($header['status']);
    }

  }

  /**
   * Get the checkbox options for the global settings.
   *
   * @return array
   *   An array with checkbox options. The keys are the element keys and the
   *   value is an array with a part of the form element definition.
   */
  private function getCheckboxOptions() {
    // Get dynamically grouped plan types.
    return [
      'sort_by_plan_type' => [
        '#title' => $this->t('Sort by plan type'),
        '#description' => $this->t('If checked, the table will be sorted first by plans being part of the GHO, then by the plan type and then by the plan name. Plan type order is: <em>@plan_types</em>. This order can be changed on the  <a href="@plan_type_url">Plan type taxonnomy page</a>.', [
          '@plan_types' => $this->getPlanTypeOrderSummary(),
          '@plan_type_url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'plan_type'])->toString(),
        ]),
      ],
      'plan_short_names' => [
        '#title' => $this->t('Use plan short names'),
        '#description' => $this->t('Check to display short names for plans on global pages. If no short name is set the normal plan name will be used as a fallback.'),
      ],
      'plan_included_in_gho_tooltip' => [
        '#title' => $this->t('Show tooltips when a plan is not included in the GHO'),
        '#description' => $this->t('If checked, a tooltip is added to the plan name column, indicating that the plan is not included in the GHO totals.'),
      ],
      'plan_type_icons' => [
        '#title' => $this->t('Show plan type icons'),
        '#description' => $this->t('If checked, icon-like flags will be added to the plan name column of plan tables on global pages. The label text is an automatically generated uppercased abbreviation based on the plan type initials, e.g. <em>Flash appeal</em> becomes <em>FA</em>.'),
      ],
      'use_latest_plan_data' => [
        '#title' => $this->t('Use latest plan data'),
        '#description' => $this->t('Check if the plan data for this homepage year should be retrieved using the argument <em>version=latest</em>. This only affects logged-in users.'),
      ],
      'funding' => [
        '#title' => $this->t('Show funding values'),
        '#description' => $this->t('Check to show funding values on global pages.'),
      ],
      'requirements' => [
        '#title' => $this->t('Show requirements values'),
        '#description' => $this->t('Check to show requirements values on global pages.'),
      ],
      'coverage' => [
        '#title' => $this->t('Show coverage values'),
        '#description' => $this->t('Check to show coverage values on global pages.'),
      ],
      'caseload_expected_reach' => [
        '#title' => $this->t('Show estimated reach values'),
        '#description' => $this->t('Check to show estimated reached values on global pages.'),
      ],
      'caseload_expected_reached' => [
        '#title' => $this->t('Show estimated reach (%)'),
        '#description' => $this->t('Check to show estimated reached %, calculated based on estimated reach and target, on global pages.'),
      ],
      'caseload_latest_reach' => [
        '#title' => $this->t('Show latest reach values'),
        '#description' => $this->t('Check to show latest reached values on global pages. This is a calculated field from the API.'),
      ],
      'caseload_reached' => [
        '#title' => $this->t('Show latest reach (%)'),
        '#description' => $this->t('Check to show reach progress, calculated based on lastest reach (calculated field) and target, on global pages.'),
      ],
      'plan_status' => [
        '#title' => $this->t('Show plan status'),
        '#description' => $this->t('Check to include the plan status in plan tables on global pages.'),
      ],
      'plan_downloads' => [
        '#title' => $this->t('Enable plan document downloads on the homepage'),
        '#description' => $this->t('Check to enable plan document downloads on global pages.'),
      ],
    ];
  }

}
