<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Component\Utility\Html;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_plans\Traits\PlanTypeTrait;
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
  public function getConfigKey() {
    return 'ghi_blocks.global_settings';
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
   * @param \Drupal\ghi_base_objects\ApiObjects\Plan[] $plans
   *   The plans.
   * @param int $year
   *   The year for which the configuration should be applied.
   */
  public function applyGlobalConfigurationPlans(array &$plans, $year) {
    $config = $this->getYearConfig($year);

    // Sort by plan type.
    if (!empty($config['sort_by_plan_type'])) {
      // Sort everything first by plan type, then by plan name.
      $type_order = $this->getAvailablePlanTypes();
      $grouped_plans = [];
      foreach ($type_order as $plan_type) {
        // Create a list of all plans for this plan type.
        foreach ($plans as $plan) {
          if (!$plan->isType($plan_type)) {
            continue;
          }
          if (empty($grouped_plans[strtolower($plan_type)])) {
            $grouped_plans[strtolower($plan_type)] = [];
          }
          $grouped_plans[strtolower($plan_type)][] = $plan;
        }
        // And sort it by plan name.
        if (!empty($grouped_plans[strtolower($plan_type)])) {
          ArrayHelper::sortObjectsByCallback($grouped_plans[strtolower($plan_type)], function ($item) use ($config) {
            return $item->getName($config['plan_short_names'] ?? FALSE);
          }, EndpointQuery::SORT_ASC, SORT_STRING);
        }
      }

      $plans = [];
      foreach ($grouped_plans as $group) {
        foreach ($group as $plan) {
          /** @var \Drupal\ghi_base_objects\ApiObjects\Plan $plan */
          $plans[$plan->getId()] = $plan;
        }
      }
    }
    else {
      // Otherwhise sort by plan name only.
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
   * @param int $year
   *   The year for which the configuration should be applied.
   * @param \Drupal\ghi_base_objects\ApiObjects\Plan[] $plans
   *   An array of plan objects.
   */
  private function applyGlobalConfigurationTable(array &$header, array &$rows, $year, array $plans) {
    $config = $this->getYearConfig($year);

    if (empty($config['caseload_reached'])) {
      // Hide the reached column.
      unset($header['reached']);
      $rows = array_map(function ($row) {
        unset($row['reached']);
        return $row;
      }, $rows);
    }

    if (empty($config['caseload_expected_reach'])) {
      // Hide the expected reach column.
      unset($header['expected_reach']);
      $rows = array_map(function ($row) {
        unset($row['expected_reach']);
        return $row;
      }, $rows);
    }

    if (empty($config['plan_downloads'])) {
      // Hide the document downloads column.
      unset($header['document']);
      $rows = array_map(function ($row) {
        unset($row['document']);
        return $row;
      }, $rows);
    }

    if (!empty($config['plan_short_names'])) {
      // Replace plan name with short name if available.
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
        /** @var \Drupal\ghi_base_objects\ApiObjects\Plan $plan */
        $plan = $plans[$plan_id];
        if (!$plan) {
          return $row;
        }
        $row['name'] = $plan->getName(TRUE);
        return $row;
      }, $rows);
    }

    if (!empty($config['plan_status_text'])) {
      // Replace plan name with short name if available.
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
        /** @var \Drupal\ghi_base_objects\ApiObjects\Plan $plan */
        $plan = $plans[$plan_id];
        if (!$plan || !$plan->getEntity() || !$plan->getEntity()->hasField('field_plan_status_string')) {
          return $row;
        }
        $plan_status_text = $plan->getEntity()->get('field_plan_status_string')->value ?? NULL;
        if ($plan_status_text) {
          $row['name'] .= Markup::create('<span class="plan-status"> (' . $plan_status_text . ')</span>');
        }
        return $row;
      }, $rows);
    }

    if (!empty($config['plan_type_icons'])) {
      // Add plan type icons to plan name column.
      $rows = ArrayHelper::arrayMapAssoc(function ($row, $plan_id) use ($plans) {
        /** @var \Drupal\ghi_base_objects\ApiObjects\Plan $plan */
        $plan = $plans[$plan_id];
        if (!$plan) {
          return $row;
        }
        $name = $row['name'];
        $row['name'] = [
          'data' => [
            [
              '#markup' => Markup::create($name),
            ],
            [
              '#theme' => 'hpc_tooltip',
              '#tooltip' => $plan->getTypeName(),
              '#tag' => 'span',
              '#tag_content' => $plan->getTypeShortName(),
              '#class' => [
                'plan-type-icon',
                Html::getClass('plan-type-' . $plan->getTypeShortName()),
              ],
            ],
          ],
        ];
        return $row;
      }, $rows);
    }

  }

  /**
   * Get disabled checkboxes.
   *
   * @return array
   *   An array with the form element keys that should be disabled.
   */
  private function getDisabledCheckboxes() {
    return [
      'use_latest_plan_data',
    ];
  }

  /**
   * Get the checkbox options for the global settings.
   *
   * @return array
   *   An array with checkbox options. The keys are the element keys and the
   *   value is an array with a part of the form element definition.
   */
  private function getCheckboxOptions() {
    return [
      'caseload_reached' => [
        '#title' => $this->t('Show reached values'),
        '#description' => $this->t('Check to show reached values on global pages.'),
      ],
      'caseload_expected_reach' => [
        '#title' => $this->t('Show expected reached values'),
        '#description' => $this->t('Check to show expected reached values on global pages.'),
      ],
      'plan_downloads' => [
        '#title' => $this->t('Enable plan document downloads on the homepage'),
        '#description' => $this->t('Check to enable plan document downloads on global pages.'),
      ],
      'plan_short_names' => [
        '#title' => $this->t('Use plan short names'),
        '#description' => $this->t('Check to display short names for plans on global pages. If no short name is set the normal plan name will be used as a fallback.'),
      ],
      'plan_type_icons' => [
        '#title' => $this->t('Show plan type icons'),
        '#description' => $this->t('If checked, icon-like flags will be added to the plan name column of plan tables on global pages. The label text is an automatically generated uppercased abbreviation based on the plan type initials, e.g. <em>Flash appeal</em> becomes <em>FA</em>.'),
      ],
      'sort_by_plan_type' => [
        '#title' => $this->t('Sort by plan type'),
        '#description' => $this->t('If checked, the table will be sorted first by the plan type, then by the plan name. Plan type order is: <em>@plan_types</em>. This order can be changed on the  <a href="@plan_type_url">Plan type taxonnomy page</a>.', [
          '@plan_types' => implode(', ', array_values($this->getAvailablePlanTypes())),
          '@plan_type_url' => Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => 'plan_type'])->toString(),
        ]),
      ],
      'use_latest_plan_data' => [
        '#title' => $this->t('Use latest plan data'),
        '#description' => $this->t('Check if the plan data for this homepage year should be retrieved using the argument <em>version=latest</em>. This only affects logged-in users.'),
      ],
      'plan_status_text' => [
        '#title' => $this->t('Show plan status'),
        '#description' => $this->t('Check to include the plan status text in plan tables on global pages.'),
      ],
    ];
  }

}
