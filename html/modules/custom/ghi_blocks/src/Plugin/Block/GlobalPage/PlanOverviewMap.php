<?php

namespace Drupal\ghi_blocks\Plugin\Block\GlobalPage;

use Drupal\Component\Render\FormattableMarkup;
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
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
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
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *    "year" = @ContextDefinition("integer", label = @Translation("Year"))
 *  }
 * )
 */
class PlanOverviewMap extends GHIBlockBase {

  use GlobalPlanOverviewBlockTrait;
  use GlobalSettingsTrait;
  use PlanFootnoteTrait;
  use GlobalMapTrait;

  const DEFAULT_DISCLAIMER = 'The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.';

  /**
   * {@inheritdoc}
   */
  public function buildContent() {

    $map = $this->buildMap();
    $chart_id = $map['chart_id'];

    return [
      '#theme' => 'plan_overview_map',
      '#chart_id' => $chart_id,
      '#map_tabs' => $map['tabs'] ? [
        '#theme' => 'item_list',
        '#items' => $map['tabs'],
        '#gin_lb_theme_suggestions' => FALSE,
      ] : NULL,
      '#attached' => [
        'library' => ['ghi_blocks/map.plan_overview'],
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
   * Build everything the map needs.
   *
   * @return array
   *   An array containing the map data, map javascript settings, the chart id
   *   and the available tabs.
   */
  private function buildMap() {
    $conf = $this->getBlockConfig();
    $chart_id = Html::getUniqueId('plan-overview-map');
    $plans = $this->getPlans();

    // All tabs we have, including the legend we want to show.
    $tabs = [
      'caseload' => [
        'group' => 'caseload',
        'label' => $this->t('People'),
        'icon' => 'users',
        'legend' => (object) [
          0 => $this->t('People in need'),
          1 => $this->t('People targeted'),
        ],
        'legend_caption' => $this->t('Donut size represents the population'),
      ],
      'funding' => [
        'group' => 'funding',
        'label' => $this->t('Funding'),
        'icon' => 'attach-money',
        'legend' => (object) [
          0 => $this->t('Requirements'),
          1 => $this->t('Funding'),
        ],
        'legend_caption' => $this->t('Donut size represents the requirements'),
      ],
    ];

    $map = [
      'chart_id' => $chart_id,
      'data' => [],
      'tabs' => [],
      'settings' => [],
      'cache_tags' => [],
    ];

    // Prepare object data per per plan.
    $country_objects = [];
    $footnotes = [];
    foreach ($plans as $plan) {
      $funding = $plan->getFunding();
      $requirements = $plan->getRequirements();

      $in_need = $plan->getCaseloadValue('inNeed');
      $target = $plan->getCaseloadValue('target');
      $reached = $plan->getCaseloadValue('latestReach');

      if (empty($funding) && empty($requirements) && empty($in_need) && empty($target)) {
        continue;
      }

      $location = $plan->getCountry();
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

      $country_objects[] = (object) [
        'plan' => $plan,
        'title' => $section && $section->isPublished() ? $section->toLink($location->name)->toString() : $location->name,
        'location' => $location,
        'funding' => (object) [
          'total_funding' => $funding,
          'total_requirements' => $requirements,
          'funding_progress' => $plan->getCoverage(),
        ],
        'caseload' => (object) [
          'total_population' => $plan->getCaseloadValue('totalPopulation'),
          'target' => $target,
          'in_need' => $in_need,
          'estimated_reach' => $plan->getCaseloadValue('expectedReach'),
          'reached' => $reached,
          'reached_percent' => !empty($reached) && !empty($target) ? 1 / $target * $reached : FALSE,
        ],
        'reporting_period' => $reached ? $plan->getLastPublishedReportingPeriod() : NULL,
      ];
    }

    // Assemble the locations and modal_contents arrays.
    $locations = [];
    $modal_contents = [];
    foreach ($country_objects as $object) {
      /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
      $plan = $object->plan;
      $plan_id = $plan->id();
      $location = [
        'location_id' => $object->location->id,
        'location_name' => $object->location->name,
        'latLng' => $object->location->latLng,
        'caseload' => [
          // These values are used to construct the donuts, the order here is
          // important.
          $object->caseload->in_need,
          $object->caseload->target,
        ],
        'funding' => [
          // These values are used to construct the donuts, the order here is
          // important.
          $object->funding->total_requirements,
          $object->funding->total_funding,
        ],
        'plan_type' => $plan->getTypeShortName(),
      ];
      $modal_contents[(string) $object->location->id] = [
        'location_id' => $object->location->id,
        'title' => $object->title,
        'tag_line' => $plan->getTypeName(),
        'html' => $this->buildCountryModal($object, !empty($footnotes[$plan_id]) ? $footnotes[$plan_id] : NULL),
      ];
      $locations[] = $location;
    }

    // Get the grouped value ranges for spot size calculation.
    $ranges_grouped = [
      'caseload' => ['min' => 0, 'max' => 0],
      'funding' => ['min' => 0, 'max' => 0],
    ];
    foreach (array_keys($tabs) as $tab_key) {
      $group = $tabs[$tab_key]['group'];
      $tab_min = array_reduce($locations, function ($carry, $item) use ($tab_key) {
        $value = is_numeric($item[$tab_key][0]) ? $item[$tab_key][0] : 0;
        return $carry > $value ? $value : $carry;
      }, 0);
      $tab_max = array_reduce($locations, function ($carry, $item) use ($tab_key) {
        $value = is_numeric($item[$tab_key][0]) ? $item[$tab_key][0] : 0;
        return $carry < $value ? $value : $carry;
      }, 0);

      $ranges_grouped[$group]['min'] = min($ranges_grouped[$group]['min'], $tab_min);
      $ranges_grouped[$group]['max'] = max($ranges_grouped[$group]['max'], $tab_max);
    }

    foreach ($locations as $location_key => $location) {
      $radius_factors = [];
      $empty_tab_values = [];
      foreach (array_keys($tabs) as $tab_key) {
        // Calculate the radius factor based on the value range in this group.
        $group = $tabs[$tab_key]['group'];
        $max = $ranges_grouped[$group]['max'];
        $relative_size = $max > 0 ? 30 / $max * $location[$tab_key][0] : 1;
        $radius_factors[$group] = $relative_size > 1 ? $relative_size : 1;
        $empty_tab_values[$group] = empty(array_sum($location[$tab_key]));
      }
      $locations[$location_key]['radius_factors'] = $radius_factors;
      $locations[$location_key]['empty_tab_values'] = $empty_tab_values;
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
        'legend' => $tab['legend'],
        'legend_caption' => $tab['legend_caption'],
      ];

      $map['tabs'][] = Markup::create('<a href="#" class="map-tab" data-map-index="' . $key . '">' . $tab['label'] . '</a>');
    }

    $map['settings'] = [
      'json' => !empty($map['data']) ? $map['data'] : NULL,
      'id' => $chart_id,
      'map_tiles_url' => $this->getStaticTilesUrlTemplate(),
      'map_style' => 'donut',
      'map_style_config' => [
        'donut_whole_segments' => [0],
        'donut_partial_segments' => [1],
      ],
      'search_enabled' => $conf['map']['search_enabled'],
      'map_disclaimer' => $conf['map']['disclaimer'],
    ];

    return $map;
  }

  /**
   * Build the content of the map modals.
   *
   * @param object $data
   *   The data object for the modal.
   * @param object $footnotes
   *   The footnotes to be used if any.
   *
   * @return string|\Drupal\Component\Render\MarkupInterface
   *   The content of the modal.
   */
  private function buildCountryModal($data, $footnotes = NULL) {
    /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
    $plan = $data->plan;
    $document_uri = $plan->getPlanDocumentUri();

    $common_theme_args = [
      'decimals' => 1,
      'use_abbreviation' => FALSE,
    ];

    $items = [
      'total_population' => [
        'label' => $this->t('Population'),
        'value' => CommonHelper::renderValue($data->caseload->total_population, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'inneed' => [
        'label' => $this->t('In need'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'in_need') . CommonHelper::renderValue($data->caseload->in_need, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'target' => [
        'label' => $this->t('Targeted'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'target') . CommonHelper::renderValue($data->caseload->target, 'amount', 'hpc_amount', $common_theme_args),
      ],
      // Note that due to space restrictions, the "estimated reach" and
      // "reached" values are mutually exclusive in the modal.
      // @see plan-overview-map-modal.tpl.php
      'estimated_reach' => [
        'label' => $this->t('Est. reach'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'estimated_reach') . CommonHelper::renderValue($data->caseload->estimated_reach, 'amount', 'hpc_amount', $common_theme_args),
      ],
      'reached' => [
        'label' => $this->t('Reached'),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'latest_reach') . CommonHelper::renderValue($data->caseload->reached, 'amount', 'hpc_amount', $common_theme_args, NULL, $this->t('Pending')),
      ],
      'reached_percent' => [
        'label' => $this->t('Reached (%)'),
        'value' => (!empty($data->reporting_period) ? ThemeHelper::render([
          '#theme' => 'hpc_tooltip',
          '#tooltip' => ThemeHelper::render([
            '#theme' => 'hpc_reporting_period',
            '#reporting_period' => $data->reporting_period,
            '#format_string' => 'Monitoring period #@period_number<br>@date_range',
          ], FALSE),
          '#class' => 'monitoring period',
          '#tag_content' => [
            '#theme' => 'hpc_icon',
            '#icon' => 'calendar_today',
            '#tag' => 'span',
          ],
        ], FALSE) : '') . CommonHelper::renderValue($data->caseload->reached_percent, 'ratio', 'hpc_percent'),
      ],
      'funding_required' => [
        'label' => (new TranslatableMarkup('Requirements')),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'requirements') . CommonHelper::renderValue($data->funding->total_requirements, 'value', 'hpc_currency', $common_theme_args),
      ],
      'funding_received' => [
        'label' => (new TranslatableMarkup('Funding')),
        'value' => $this->getRenderedFootnoteTooltip($footnotes, 'funding') . CommonHelper::renderValue($data->funding->total_funding, 'value', 'hpc_currency', $common_theme_args),
      ],
      'funding_progress' => [
        'label' => $this->t('Coverage'),
        'value' => ThemeHelper::render([
          '#theme' => 'hpc_percent',
          '#percent' => $data->funding->funding_progress,
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
                'data-tippy-content' => $this->t('Download the @type document', [
                  '@type' => strtolower($plan->getTypeShortName()) == 'other' ? $this->t('plan') : $plan->getTypeShortName(),
                ]),
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
      unset($items['estimated_reach']);
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
      'plans' => [
        'include_method' => 'plan_type',
        'plan_types' => $this->getDefaultPlanTypes(),
        'plan_select' => [],
      ],
      'map' => [
        'search_enabled' => FALSE,
        'disclaimer' => self::DEFAULT_DISCLAIMER,
      ],
    ];
  }

  /**
   * Get the default plan types.
   *
   * @return array
   *   An array with the term ids of the default plan types to be used.
   */
  private function getDefaultPlanTypes() {
    $plan_types = $this->getAvailablePlanTypes();
    $plan_types_flipped = array_flip($plan_types);
    return [
      $plan_types_flipped['Humanitarian response plan'],
      $plan_types_flipped['Flash appeal'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
    ];

    $form['plans'] = [
      '#type' => 'details',
      '#title' => $this->t('Plans'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];

    // As per HPC-7563, the user should be given an option to select which plan
    // type is to be shown.
    $form['plans']['include_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Include plans based on'),
      '#options' => [
        'plan_type' => $this->t('Plan type'),
        'plan_select' => $this->t('Select plans manually'),
      ],
      '#description' => $this->t('Only a single plan per country is currently supported.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'include_method',
      ]),
    ];
    $form['plans']['plan_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Plan type'),
      '#description' => $this->t('Select the plan types to be included in the map. If there are multiple plans for a country in the dataset, only a single plan will be displayed. The plan to be retained is determined by the plan type in the order shown above.'),
      '#options' => $this->getAvailablePlanTypes(TRUE),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'plan_types',
      ]),
      '#states' => [
        'visible' => [
          ':input[name="basic[plans][include_method]"]' => ['value' => 'plan_type'],
        ],
      ],
    ];

    // Manual selection of plan type for country.
    $plans_by_country = $this->getPlansByCountry();
    $form['plans']['plan_select'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          ':input[name="basic[plans][include_method]"]' => ['value' => 'plan_select'],
        ],
      ],
    ];
    $form['plans']['plan_select']['countries'] = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [
        $this->t('Country'),
        $this->t('Enabled'),
        $this->t('Available plans'),
      ],
    ];

    foreach ($plans_by_country as $country_id => $country) {
      if (empty($country['plans'])) {
        continue;
      }
      $form['plans']['plan_select']['countries'][$country_id] = [];
      $form['plans']['plan_select']['countries'][$country_id]['country'] = [
        '#markup' => $country['name'],
      ];
      $form['plans']['plan_select']['countries'][$country_id]['status'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enabled'),
        '#title_display' => 'invisible',
        '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
          'plans',
          'plan_select',
          'countries',
          $country_id,
          'status',
        ]) ?? TRUE,
      ];
      $default_plan = $this->getDefaultFormValueFromFormState($form_state, [
        'plans',
        'plan_select',
        'countries',
        $country_id,
        'plan',
      ]);
      $form['plans']['plan_select']['countries'][$country_id]['plan'] = [
        '#type' => 'radios',
        '#title' => $country['name'],
        '#title_display' => 'invisible',
        '#options' => $country['plans'],
        '#default_value' => $default_plan !== NULL && in_array($default_plan, $country['plans']) ? $default_plan : array_key_first($country['plans']),
        '#states' => [
          'visible' => [
            ':input[name="basic[plans][plan_select][countries][' . $country_id . '][status]"]' => ['checked' => TRUE],
          ],
        ],
      ];
      if (count($country['plans']) == 1) {
        $form['plans']['plan_select']['countries'][$country_id]['plan']['#disabled'] = TRUE;
      }
    }

    $form['map'] = [
      '#type' => 'details',
      '#title' => $this->t('Map'),
      '#description' => $this->t('The following settings allow you to toggle some features for <em>this single map instance</em>. More <em>global settings</em>, that apply to various page elements across a year, can be controlled on the <a href="@url" target="_blank">GHI Global settings page</a>.', [
        '@url' => Url::fromRoute('ghi_blocks.global_config', [], ['query' => ['year' => $this->getContextValue('year')]])->toString(),
      ]),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $form['map']['search_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add search box'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'map',
        'search_enabled',
      ]),
      '#description' => $this->t('Check this if an additonal search box should be added to the top left corner of the map.'),
    ];
    $form['map']['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'map',
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
    $config = $this->getBlockConfig();

    if ($config['plans']['include_method'] == 'plan_select' && !empty($config['plans']['plan_select'])) {
      // Filter based on selected plan types.
      $plan_select = $config['plans']['plan_select']['countries'] ?? NULL;
      $selected_plan_ids = $plan_select ? array_filter(array_map(function ($item) {
        return $item['status'] ? $item['plan'] : NULL;
      }, $plan_select)) : NULL;
      $plans = array_filter($plans, function ($plan) use ($selected_plan_ids) {
        return $selected_plan_ids === NULL || in_array($plan->id(), $selected_plan_ids);
      });
    }
    elseif (!empty($config['plans']['plan_types'])) {
      // Filter based on selected plan types.
      $selected_plan_type_tids = array_filter($config['plans']['plan_types']);
      $plans = array_filter($plans, function ($plan) use ($selected_plan_type_tids) {
        $term = $this->getTermObjectByName($plan->getOriginalTypeName(), $plan->isTypeIncluded());
        return $term && in_array($term->id(), $selected_plan_type_tids);
      });
      $plans = $this->reduceCountryPlans($plans);
    }

    // Apply the global configuration to limit the source data.
    $this->applyGlobalConfigurationPlans($plans, $this->getContextValue('year'));

    return $plans;
  }

  /**
   * Look if there are multiple plans per country and reduce that to one.
   *
   * Which plan is kept, depends on the plan type.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[] $plans
   *   The array of plan objects to check.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan[]
   *   The array of plan objects, reduced to one plan per country.
   */
  private function reduceCountryPlans(array $plans) {
    $countries = [];
    foreach ($plans as $plan) {
      $country = $plan->getCountry();
      if (!$country) {
        continue;
      }
      if (!array_key_exists($country->id, $countries)) {
        $countries[$country->id] = [];
      }
      $countries[$country->id][$plan->id()] = $plan->getTypeName();
    }
    $plan_types = $this->getAvailablePlanTypes();
    $plans = array_filter($plans, function ($plan) use ($countries, $plan_types) {
      /** @var \Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan $plan */
      $country = $plan->getCountry();
      if (!$country) {
        return TRUE;
      }
      if (count($countries[$country->id]) <= 1) {
        return TRUE;
      }
      foreach ($plan_types as $type_name) {
        $key = array_search($type_name, $countries[$country->id]);
        if ($key === FALSE) {
          continue;
        }
        return $key == $plan->id();
      }
      return TRUE;
    });
    return $plans;
  }

  /**
   * Get plans by country.
   *
   * @return array
   *   An array of plans by country, keyed by country id, values being an
   *   array of with the id and the name of the country. Each country has an
   *   additional 'plans' key with an array of plans for that country, keyed by
   *   plan id and the value being the plan name.
   */
  private function getPlansByCountry() {
    $plans = $this->getPlanQuery()->getPlans();
    if (empty($plans)) {
      return [];
    }
    $countries = [];
    foreach ($plans as $plan) {
      $plan_countries = $plan->getCountries();
      if (count($plan_countries) > 1) {
        // Skip this.
        continue;
      }
      $plan_country = reset($plan_countries);
      if (!array_key_exists($plan_country->id, $countries)) {
        $countries[$plan_country->id] = [
          'id' => $plan_country->id,
          'name' => $plan_country->name,
          'plans' => [],
        ];
      }
      $countries[$plan_country->id]['plans'][$plan->id()] = new FormattableMarkup('@plan_name (@plan_type, @plan_status)', [
        '@plan_name' => $plan->getName(),
        '@plan_type' => $plan->getTypeShortName(),
        '@plan_status' => $plan->getPlanStatusLabel(),
      ]);
    }
    ArrayHelper::sortArrayByStringKey($countries, 'name', EndpointQuery::SORT_ASC);
    return $countries;
  }

}
