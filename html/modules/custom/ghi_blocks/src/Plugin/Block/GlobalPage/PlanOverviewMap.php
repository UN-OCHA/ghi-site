<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\GlobalMapTrait;
use Drupal\ghi_blocks\Traits\GlobalPlanOverviewBlockTrait;
use Drupal\ghi_blocks\Traits\GlobalSettingsTrait;
use Drupal\ghi_blocks\Traits\PlanFootnoteTrait;
use Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan;
use Drupal\ghi_plans\Entity\PlanType;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_downloads\Helpers\DownloadHelper;

/**
 * Provides a 'PlanOverviewMap' block.
 *
 * @Block(
 *  id = "global_plan_overview_map",
 *  admin_label = @Translation("Plan overview map"),
 *  category = @Translation("Global"),
 *  data_sources = {
 *    "plans" = "plan_overview_query",
 *    "locations" = "locations_query",
 *    "countries" = "country_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class PlanOverviewMap extends GHIBlockBase {

  use GlobalMapTrait;
  use GlobalPlanOverviewBlockTrait;
  use GlobalSettingsTrait;
  use PlanFootnoteTrait;

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $map = $this->buildCircleMap();

    $chart_id = $map['chart_id'];
    return [
      '#theme' => 'plan_overview_map',
      '#chart_id' => $chart_id,
      '#map_type' => $map['settings']['style'],
      '#map_tabs' => $map['tabs'] ? [
        '#theme' => 'item_list',
        '#items' => $map['tabs'],
        '#gin_lb_theme_suggestions' => FALSE,
      ] : NULL,
      '#attached' => [
        'library' => ['ghi_blocks/map.gl.plan_overview'],
        'drupalSettings' => [
          'plan_overview_map' => [
            $chart_id => $map['settings'],
          ],
        ],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags($map['cache_tags'], $this->getMapConfigCacheTags()),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $plans = $this->getPlans();
    foreach ($plans as $plan) {
      $plan_entity = $plan->getEntity();
      if (!$plan_entity) {
        continue;
      }
      $cache_tags = Cache::mergeTags($cache_tags, $plan_entity->getCacheTags());
    }
    return $cache_tags;
  }

  /**
   * Build everything that a circle map needs.
   *
   * @return array
   *   An array containing the map data, map javascript settings, the chart id
   *   and the available tabs.
   */
  private function buildCircleMap() {
    $conf = $this->getBlockConfig();
    $chart_id = Html::getUniqueId('plan-overview-map');
    $plans = $this->getPlans();

    // All tabs we have, including the legend we want to show.
    $tabs = [
      'in_need' => [
        'group' => 'caseload',
        'label' => $this->t('In Need'),
        'icon' => 'users',
      ],
      'target' => [
        'group' => 'caseload',
        'label' => $this->t('Targeted'),
        'icon' => 'users',
      ],
      'requirements' => [
        'group' => 'funding',
        'label' => $this->t('Requirements'),
        'icon' => 'attach-money',
      ],
      'funding' => [
        'group' => 'funding',
        'label' => $this->t('Funding'),
        'icon' => 'attach-money',
      ],
      'coverage' => [
        'group' => 'coverage',
        'label' => $this->t('% Funded'),
        'icon' => 'attach-money',
      ],
    ];

    $map = [
      'chart_id' => $chart_id,
      'data' => [],
      'tabs' => [],
      'settings' => [],
      'cache_tags' => [],
    ];

    $common_theme_args = [
      'scale' => 'full',
      'use_abbreviation' => FALSE,
    ];

    /** @var \Drupal\ghi_base_objects\Plugin\EndpointQuery\CountryQuery $country_query */
    $country_query = $this->getQueryHandler('countries');

    // Assemble the locations and modal_contents arrays.
    $locations = [];
    $all_countries = [];
    $modal_contents = [];
    $footnotes = [];

    $this->sortPlansByPlanType($plans, $config['plan_short_names'] ?? FALSE);

    foreach ($plans as $plan) {
      $funding = $plan->getFunding();
      $requirements = $plan->getRequirements();

      $in_need = $plan->getCaseloadValue('inNeed');
      $target = $plan->getCaseloadValue('target');
      $reached = $plan->getCaseloadValue('latestReach');
      $expected_reach = $plan->getCaseloadValue('expectedReach');

      if (empty($funding) && empty($requirements) && empty($in_need) && empty($target)) {
        continue;
      }

      $location = $this->getPlanLocation($plan);
      if (!$location) {
        continue;
      }

      $plan_entity = $plan->getEntity();
      if ($plan_entity) {
        $footnotes[$plan->id()] = $this->getFootnotesForPlanBaseobject($plan_entity);
      }
      $section = $plan_entity ? $this->sectionManager->loadSectionForBaseObject($plan_entity) : NULL;
      if ($section) {
        $map['cache_tags'] = Cache::mergeTags($map['cache_tags'], $section->getCacheTags());
      }

      $caseload = (object) [
        'total_population' => $plan->getCaseloadValue('totalPopulation'),
        'target' => $target,
        'in_need' => $in_need,
        'expected_reach' => $expected_reach,
        'expected_reached' => !empty($expected_reach) && !empty($target) ? $expected_reach / $target : FALSE,
        'reached' => $reached,
        'reached_percent' => !empty($reached) && !empty($target) ? $reached / $target : FALSE,
      ];
      $funding = (object) [
        'total_funding' => $funding,
        'total_requirements' => $requirements,
        'funding_progress' => $plan->getCoverage(),
      ];

      $plan_id = $plan->id();
      $object_id = count($locations) + 1;
      $object_title = $section && $section->isPublished() ? $section->toLink($plan_entity->getShortName())->toString() : $plan_entity->getShortName();
      $reporting_period = $reached ? $plan->getLastPublishedReportingPeriod() : NULL;

      // Assemble the highlight countries.
      $highlight_countries = [];
      if ($this->useCountryOutlines()) {
        if (!$plan->isRrp()) {
          $highlight_countries[(int) $location->id()] = (int) $location->id();
        }
        $highlight_countries += array_map(function ($item) {
          return $item->id();
        }, $plan->getCountries());
        $all_countries += $highlight_countries;
      }

      // Build the location object and add it to the location list.
      $locations[$object_id] = [
        'object_id' => $object_id,
        'object_title' => $plan_entity->getShortName(),
        'sort_key' => Html::getUniqueId($location->getName() . '-' . $plan->getTypeOrder()),
        'location_id' => $location->id(),
        'location_name' => $location->getName(),
        'highlight_countries' => array_values($highlight_countries),
        'latLng' => $location->getLatLng(),
        'in_need' => $caseload->in_need,
        'target' => $caseload->target,
        'requirements' => $funding->total_requirements,
        'funding' => $funding->total_funding,
        'coverage' => $funding->funding_progress,
        'tooltip' => implode(' ', [
          $plan_entity->getShortName(),
          $plan_entity->getYear(),
          $plan->getTypeShortName(),
        ]),
        'tooltip_values' => [
          'in_need' => [
            'label' => $tabs['in_need']['label'],
            'value' => CommonHelper::renderValue($caseload->in_need, 'amount', 'hpc_amount', $common_theme_args),
          ],
          'target' => [
            'label' => $tabs['target']['label'],
            'value' => CommonHelper::renderValue($caseload->target, 'amount', 'hpc_amount', $common_theme_args),
          ],
          'requirements' => [
            'label' => $tabs['requirements']['label'],
            'value' => CommonHelper::renderValue($funding->total_requirements, 'value', 'hpc_currency', $common_theme_args),
          ],
          'funding' => [
            'label' => $tabs['funding']['label'],
            'value' => CommonHelper::renderValue($funding->total_funding, 'value', 'hpc_currency', $common_theme_args),
          ],
          'coverage' => [
            'label' => $tabs['coverage']['label'],
            'value' => CommonHelper::renderValue($funding->funding_progress, 'percent', 'hpc_percent', $common_theme_args),
          ],
        ],
        'plan_type' => strtolower($plan->getPlanType()?->getAbbreviation() ?? ''),
      ];
      $modal_contents[(string) $object_id] = [
        'object_id' => $object_id,
        'location_id' => $location->id(),
        'title' => $object_title,
        'tag_line' => $plan->getTypeName(),
        'html' => $this->buildCountryModal($plan, $caseload, $funding, $reporting_period, !empty($footnotes[$plan_id]) ? $footnotes[$plan_id] : NULL),
      ];
    }

    // Add the offsets chain to each location item, so that the map can display
    // plans on the same location together.
    $location_plans_sorted = [];
    foreach ($locations as $location) {
      $location_plans_sorted[$location['location_id']][$location['object_id']] = $location['object_id'];
    }
    $offset_chain = [];
    foreach ($location_plans_sorted as $location_id => $object_ids) {
      asort($object_ids);
      foreach (array_keys($object_ids) as $object_id) {
        $offset_chain[$location_id][] = $object_id;
      }
    }

    foreach ($locations as &$location) {
      $location['offset_chain'] = $offset_chain[$location['location_id']];
      if (count($offset_chain[$location['location_id']]) == 1) {
        $location['offset_chain'] = [];
      }
      foreach ($location['offset_chain'] as $key => $object_id) {
        $chain_item = $locations[$object_id];
        if ($chain_item['object_id'] > $location['object_id']) {
          unset($location['offset_chain'][$key]);
        }
      }
      // Re-key the indexes, to have an array available in javascript, which is
      // what the client code expects.
      $location['offset_chain'] = array_values($location['offset_chain']);
    }

    // Re-key the indexes, to have an array available in javascript, which is
    // what the client code expects.
    $locations = array_values($locations);

    // Get the grouped value ranges for spot size calculation.
    $ranges_grouped = [
      'caseload' => ['min' => 0, 'max' => 0],
      'funding' => ['min' => 0, 'max' => 0],
      'coverage' => ['min' => 0, 'max' => 0],
    ];
    $this->calculateGroupedSizes($locations, $tabs, $ranges_grouped);

    // Get the geojson data for every country we need to show on the map.
    $geojson = [];
    foreach ($all_countries as $location_id) {
      if (array_key_exists($location_id, $geojson)) {
        continue;
      }
      $country_location = $country_query->getCountry($location_id);
      if ($country_location) {
        $geojson[$location_id] = $country_location->toArray();
      }
    }

    foreach ($tabs as $key => $tab) {
      // Set the radius factor for each location based on the predetermined
      // factors per map tab.
      array_walk($locations, function (&$item) use ($key) {
        $item['radius_factor'] = $item['radius_factors'][$key];
      });
      $map['data'][$key] = [
        'locations' => $locations,
        'modal_contents' => $modal_contents,
        'geojson' => $geojson,
      ];
      $map['tabs'][] = Markup::create('<a href="#" class="map-tab" data-map-index="' . $key . '">' . $tab['label'] . '</a>');
    }

    $map['settings'] = [
      'json' => !empty($map['data']) ? $map['data'] : NULL,
      'id' => $chart_id,
      'style' => 'circle',
      'legend' => $this->buildLegendItems(),
      'search_enabled' => $conf['search_enabled'],
      'disclaimer' => $conf['disclaimer'] ?: $this->getDefaultMapDisclaimer(),
    ];

    return $map;
  }

  /**
   * Build the legend items.
   *
   * @return array
   *   An array of legend items. The keys are the plan type abbreviations, the
   *   values the labels.
   */
  private function buildLegendItems() {
    $plans = $this->getPlans();

    $plan_types = [];
    foreach ($plans as $plan) {
      if (!$plan->getPlanType() || !empty($plan_types[$plan->getPlanType()->id()])) {
        continue;
      }
      $plan_types[$plan->getPlanType()->id()] = $plan->getPlanType();
    }
    $plan_type_names = array_map(function (PlanType $plan_type) {
      return $plan_type->label();
    }, $plan_types);
    $plan_type_keys = array_map(function (PlanType $plan_type) {
      return strtolower($plan_type->getAbbreviation());
    }, $plan_types);

    $legend = array_combine($plan_type_keys, $plan_type_names);
    unset($legend['cap']);
    return $legend;
  }

  /**
   * Build the content of the map modals.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan
   *   The plan object for the modal.
   * @param object $caseload
   *   The caseload object for the modal.
   * @param object $funding
   *   The funding object for the modal.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod $reporting_period
   *   The reporting period object for the modal.
   * @param object $footnotes
   *   The footnotes to be used if any.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The content of the modal.
   */
  private function buildCountryModal(PlanOverviewPlan $plan, $caseload, $funding, $reporting_period, $footnotes = NULL) {
    $document_uri = $plan->getPlanDocumentUri();

    $common_theme_args = [
      'decimals' => 1,
      'use_abbreviation' => FALSE,
    ];

    $items = [
      'total_population' => [
        'label' => $this->t('Population'),
        'value' => CommonHelper::renderValue($caseload->total_population, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'inneed' => [
        'label' => $this->t('In need'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'in_need') . CommonHelper::renderValue($caseload->in_need, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'target' => [
        'label' => $this->t('Targeted'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'target') . CommonHelper::renderValue($caseload->target, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'expected_reach' => [
        'label' => $this->t('Est. reach'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'expected_reach') . CommonHelper::renderValue($caseload->expected_reach, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'expected_reached' => [
        'label' => $this->t('Est. reach (%)'),
        'value' => CommonHelper::renderValue($caseload->expected_reached, 'ratio', 'hpc_percent'),
      ],
      'reached' => [
        'label' => $this->t('Reached'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'latest_reach') . CommonHelper::renderValue($caseload->reached, 'amount', 'hpc_amount', $common_theme_args, NULL, $this->t('Pending')),
      ],
      'reached_percent' => [
        'label' => $this->t('Reached (%)'),
        'value' => (!empty($reporting_period) ? ThemeHelper::render([
          '#theme' => 'hpc_tooltip',
          '#tooltip' => $reporting_period->format('Monitoring period #@period_number<br>@date_range'),
          '#class' => 'monitoring-period',
          '#tag_content' => [
            '#theme' => 'hpc_icon',
            '#icon' => 'calendar_today',
            '#tag' => 'span',
          ],
        ], FALSE) : '') . CommonHelper::renderValue($caseload->reached_percent, 'ratio', 'hpc_percent'),
      ],
      'funding_required' => [
        'label' => (new TranslatableMarkup('Requirements')),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'requirements') . CommonHelper::renderValue($funding->total_requirements, 'value', 'hpc_currency', $common_theme_args),
      ],
      'funding_received' => [
        'label' => (new TranslatableMarkup('Funding')),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'funding') . CommonHelper::renderValue($funding->total_funding, 'value', 'hpc_currency', $common_theme_args),
      ],
      'funding_progress' => [
        'label' => $this->t('% Funded'),
        'value' => ThemeHelper::render([
          '#theme' => 'hpc_percent',
          '#percent' => $funding->funding_progress,
        ]),
      ],
      'plan_status' => [
        'label' => $this->t('Status'),
        'value' => ThemeHelper::render([
          '#type' => 'container',
          'content' => array_filter([
            'plan_status' => [
              '#theme' => 'plan_status',
              '#compact' => FALSE,
              '#status' => strtolower($plan->getPlanStatus() ? 'published' : 'unpublished'),
              '#status_label' => $plan->getPlanStatusLabel(),
            ],
            'document' => $document_uri ? [
              '#type' => 'html_tag',
              '#tag' => 'span',
              '#attributes' => [
                'data-toggle' => 'tooltip',
                'data-tippy-content' => $this->t('Download the plan document'),
              ],
              'content' => DownloadHelper::getDownloadIcon($document_uri),
            ] : NULL,
          ]),
        ], FALSE),
      ],
    ];

    $global_config = $this->getYearConfig($this->getContextValue('year'));
    if (empty($global_config['funding'])) {
      unset($items['funding_received']);
    }
    if (empty($global_config['requirements'])) {
      unset($items['funding_required']);
    }
    if (empty($global_config['coverage'])) {
      unset($items['funding_progress']);
    }
    if (empty($global_config['caseload_expected_reach'])) {
      unset($items['expected_reach']);
    }
    if (empty($global_config['caseload_expected_reached'])) {
      unset($items['expected_reached']);
    }
    if (empty($global_config['caseload_latest_reach'])) {
      unset($items['reached']);
    }
    if (empty($global_config['caseload_reached'])) {
      unset($items['reached_percent']);
    }

    $build = [
      '#theme' => 'plan_overview_map_modal',
      '#items' => $items,
    ];
    return Markup::create(ThemeHelper::render($build, FALSE));
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'style' => 'circle',
      'search_enabled' => FALSE,
      'disclaimer' => NULL,
      'plan_select' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The following settings allow you to toggle some features for <em>this single map instance</em>. More <em>global settings</em>, that apply to various page elements across a year, can be controlled on the <a href=":url" target="_blank">GHI Global settings page</a>.', [
        ':url' => Url::fromRoute('ghi_blocks.global_config', [], ['query' => ['year' => $this->getContextValue('year')]])->toString(),
      ]),
    ];
    $form['search_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add search box'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'search_enabled',
      ]),
      '#description' => $this->t('Check this if an additonal search box should be added to the top left corner of the map.'),
    ];
    $form['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'disclaimer',
      ]),
    ];
    return $form;
  }

  /**
   * Retrieve the plans to display in this block.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   Array of plan objects.
   */
  private function getPlans() {
    $plans = $this->getPlanQuery()->getPlans();
    if (empty($plans)) {
      return $plans;
    }

    // Apply the global configuration to limit the source data.
    $this->applyGlobalConfigurationPlans($plans, $this->getContextValue('year'));
    return $plans;
  }

  /**
   * Get the location for the given plan partial.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan
   *   The plan partial object.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   An object describing the map location or NULL.
   */
  private function getPlanLocation(PlanOverviewPlan $plan) {
    $plan_entity = $plan->getEntity();
    $default_country = $plan->getCountry();
    return $plan_entity->getFocusCountryMapLocation($default_country) ?? $default_country;
  }

  /**
   * Calculate the grouped size of each location item.
   *
   * @param array $locations
   *   An array of location objects.
   * @param array $tabs
   *   The tabs used on the map.
   * @param array $ranges_grouped
   *   Grouped ranges.
   */
  private function calculateGroupedSizes(&$locations, $tabs, $ranges_grouped) {
    foreach (array_keys($tabs) as $tab_key) {
      $group = $tabs[$tab_key]['group'];
      $tab_min = array_reduce($locations, function ($carry, $item) use ($tab_key) {
        $value = is_numeric($item[$tab_key]) ? $item[$tab_key] : 0;
        return $carry > $value ? $value : $carry;
      }, 0);
      $tab_max = array_reduce($locations, function ($carry, $item) use ($tab_key) {
        $value = is_numeric($item[$tab_key]) ? $item[$tab_key] : 0;
        return $carry < $value ? $value : $carry;
      }, 0);

      $ranges_grouped[$group]['min'] = min($ranges_grouped[$group]['min'], $tab_min);
      $ranges_grouped[$group]['max'] = max($ranges_grouped[$group]['max'], $tab_max);
    }

    foreach ($locations as &$location) {
      $radius_factors = [];
      $empty_tab_values = [];
      foreach (array_keys($tabs) as $tab_key) {
        // Calculate the radius factor based on the value range in this group.
        $group = $tabs[$tab_key]['group'];
        $max = $ranges_grouped[$group]['max'];
        $relative_size = ($max > 0 ? 10 / $max * $location[$tab_key] : 1) * 3;
        $radius_factors[$tab_key] = $relative_size > 1 ? $relative_size : 1;
        $empty_tab_values[$tab_key] = empty($location[$tab_key]);
      }
      $location['radius_factors'] = $radius_factors;
      $location['empty_tab_values'] = $empty_tab_values;
    }
  }

}
