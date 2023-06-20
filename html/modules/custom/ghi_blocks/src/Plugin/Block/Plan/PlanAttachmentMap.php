<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_blocks\Traits\GlobalMapTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

/**
 * Provides a 'PlanAttachmentMap' block.
 *
 * @Block(
 *  id = "plan_attachment_map",
 *  admin_label = @Translation("Attachment Map"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "attachment" = "attachment_query",
 *    "attachment_search" = "attachment_search_query",
 *  },
 *  default_title = @Translation("Data by location"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "attachments" = {
 *      "title" = @Translation("Attachments"),
 *      "callback" = "attachmentsForm"
 *    },
 *    "map" = {
 *      "title" = @Translation("Map"),
 *      "callback" = "mapForm",
 *      "base_form" = TRUE
 *    }
 *  }
 * )
 */
class PlanAttachmentMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface {

  use PlanReportingPeriodTrait;
  use BlockCommentTrait;
  use GlobalMapTrait;

  const STYLE_CIRCLE = 'circle';
  const STYLE_DONUT = 'donut';

  const DONUT_DISPLAY_VALUE_FULL = 'full';
  const DONUT_DISPLAY_VALUE_PERCENTAGE = 'percentage';
  const DONUT_DISPLAY_VALUE_PARTIAL = 'partial';

  const DEFAULT_DISCLAIMER = 'The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.';

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $attachment = $this->getDefaultAttachment();
    if (!$attachment) {
      // Nothing to show.
      return NULL;
    }

    $conf = $this->getBlockConfig();
    $map_style = $conf['map']['appearance']['style'];
    $chart_id = Html::getUniqueId('plan-attachment-map--' . $map_style);

    if ($map_style == self::STYLE_CIRCLE) {
      $map = $this->buildCircleMap();
    }
    else {
      $map = $this->buildDonutMap();
    }

    if (empty($map['data'])) {
      // Nothing to show.
      return NULL;
    }
    $map_settings = [
      // If the map data is empty, it is important to set it to NULL, otherwhise
      // the empty array is simply ignored due to the way that Drupal merges the
      // given settings into the existing ones.
      'json' => !empty($map['data']) ? $map['data'] : NULL,
      'id' => $chart_id,
      'map_tiles_url' => $this->getStaticTilesUrlTemplate(),
      'disclaimer' => $conf['map']['common']['disclaimer'] ?? self::DEFAULT_DISCLAIMER,
      'pcodes_enabled' => $conf['map']['common']['pcodes_enabled'] ?? TRUE,
      'map_style' => $map_style,
    ] + $map['settings'];

    $attachment_switcher = $this->getAttachmentSwitcher();

    $build = [
      '#full_width' => FALSE,
    ];
    $build[] = [
      '#theme' => 'plan_attachment_map',
      '#chart_id' => $chart_id,
      '#map_tabs' => $map['tabs'] ?? NULL,
      '#map_type' => $map_style,
      '#attachment_switcher' => $attachment_switcher,
      '#legend' => $map_style == self::STYLE_CIRCLE ? FALSE : TRUE,
      '#attached' => [
        'library' => ['ghi_blocks/map.plan'],
        'drupalSettings' => [
          'plan_attachment_map' => [
            $chart_id => $map_settings,
          ],
        ],
      ],
      '#cache' => [
        'tags' => Cache::mergeTags($this->getCurrentBaseObject()->getCacheTags(), $this->getMapConfigCacheTags()),
      ],
    ];
    $comment = $this->buildBlockCommentRenderArray($conf['map']['common']['comment'] ?? NULL);
    if ($comment) {
      $comment['#attributes']['class'][] = 'content-width';
      $build['comment'] = $comment;
    }
    return $build;
  }

  /**
   * Map builder for circle maps.
   */
  private function buildCircleMap() {
    $map = [
      'data' => [],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => [],
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      'settings' => [],
    ];

    $attachment = $this->getDefaultAttachment();
    $plan_base_object = $this->getCurrentPlanObject();
    $plan_id = $this->getCurrentPlanId();
    $decimal_format = $plan_base_object->getDecimalFormat();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id);
    $reporting_periods_rendered = array_map(function ($reporting_period) {
      return ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $reporting_period,
        '#format_string' => 'Monitoring period #@period_number: @date_range',
      ]);
    }, $reporting_periods);
    $reporting_period = $this->getCurrentReportingPeriod();
    $configured_reporting_periods = $this->getConfiguredReportingPeriods();

    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period, TRUE);
    foreach ($disaggregated_data as $metric_index => $metric_item) {
      if (empty($metric_item['locations'])) {
        continue;
      }
      $metric_label = $this->getMetricLabel($metric_index);
      $metric_type = strtolower($metric_item['metric']->type);
      $metric_map_key = $metric_type . '-' . $metric_index;
      $metric_map_data = $this->prepareMetricItemMapData($metric_index, $metric_item, $decimal_format, $reporting_period ? $reporting_periods[$reporting_period] : NULL);
      $map['data'][$metric_map_key] = [
        'label' => $metric_label,
        'metric' => $metric_item['metric'],
        'unit_type' => $metric_item['unit_type'],
        'locations' => array_values($metric_map_data['location_data']),
        'modal_contents' => $metric_map_data['modal_contents'],
        'variants' => [],
      ];
    }

    if (empty($map['data'])) {
      // No data, no widget.
      return $map;
    }

    // If more than one monitoring periods have been selected, add a a variant
    // drop-down.
    if (count($configured_reporting_periods) > 1) {
      $disaggregated_data_multiple_periods = $attachment->getDisaggregatedDataMultiple($configured_reporting_periods, FALSE, FALSE);
      if (!empty($disaggregated_data_multiple_periods)) {
        foreach ($disaggregated_data_multiple_periods as $reporting_period_id => $period_data) {
          // Using the reporting period id from the reporting period object,
          // because $reporting_period_id can also be latest and we don't want
          // duplicates in the list.
          $reporting_period_id = $period_data['reporting_period']->id;
          foreach ($period_data['disaggregated_data'] as $metric_index => $metric_item) {
            $metric_type = strtolower($metric_item['metric']->type);
            $metric_map_key = $metric_type . '-' . $metric_index;
            if (empty($map['data'][$metric_map_key])) {
              continue;
            }
            if (empty($metric_item['locations'])) {
              continue;
            }
            if (!empty($map['data'][$metric_map_key]['variants'][$reporting_period_id])) {
              continue;
            }
            if (!$attachment->isMeasurementField($metric_item['metric']->name->en)) {
              continue;
            }
            $metric_map_data = $this->prepareMetricItemMapData($metric_index, $metric_item, $decimal_format, $period_data['reporting_period']);
            $map['data'][$metric_map_key]['variants'][$reporting_period_id] = [
              'label' => $reporting_periods_rendered[$reporting_period_id],
              'tab_label' => $period_data['reporting_period']->periodNumber,
              'locations' => $metric_map_data['location_data'],
              'modal_contents' => $metric_map_data['modal_contents'],
            ];
          }
        }
      }
    }
    // Build the map tabs.
    foreach ($map['data'] as $key => $item) {
      // Display a variant drop-down for measurement metrics if variants are
      // present and if there this more than 1.
      if (!empty($item['variants']) && count($item['variants']) > 1 && $attachment->isMeasurementField($item['metric']->name->en)) {
        $variant_options = [];
        foreach ($item['variants'] as $variant_id => $variant) {
          $variant_options[] = [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => [
              'data-variant-tab-label' => $variant['tab_label'],
              'data-variant-id' => $variant_id,
            ],
            [
              '#markup' => Markup::create($variant['label']),
            ],
          ];
        }
        $first_variant = reset($item['variants']);
        $map['tabs']['#items'][] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => [
              'class' => ['map-tab'],
              'data-map-index' => $key,
            ],
            [
              '#markup' => Markup::create($item['label']),
            ],
          ],
          [
            '#theme' => 'ghi_dropdown',
            '#toggle_label' => '#' . $first_variant['tab_label'],
            '#options' => $variant_options,
          ],
        ];
      }
      else {
        // Otherwhise just display a tab link.
        $map['tabs']['#items'][] = [
          [
            '#type' => 'html_tag',
            '#tag' => 'a',
            '#attributes' => [
              'class' => ['map-tab'],
              'data-map-index' => $key,
            ],
            [
              '#markup' => Markup::create($item['label']),
            ],
          ],
        ];
      }
    }

    return $map;
  }

  /**
   * Map builder for donut maps.
   */
  private function buildDonutMap() {
    $map = [
      'data' => [
        'attachment' => [
          'locations' => [],
          'modal_contents' => [],
          'measurements' => [],
          'measurement_metrics' => [],
          'legend' => [],
        ],
      ],
      'tabs' => NULL,
      'settings' => [],
    ];

    $attachment = $this->getDefaultAttachment();
    $plan_base_object = $this->getCurrentPlanObject();
    $plan_id = $this->getCurrentPlanId();
    $decimal_format = $plan_base_object->getDecimalFormat();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id);
    $reporting_periods_rendered = array_map(function ($reporting_period) {
      return ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $reporting_period,
        '#format_string' => '#@period_number: @date_range',
      ]);
    }, $reporting_periods);
    $reporting_period = $this->getCurrentReportingPeriod();
    $configured_reporting_periods = $this->getConfiguredReportingPeriods();

    $unit_label = $attachment->unit->label ?? NULL;
    $unit_group = $attachment->unit->group ?? NULL;

    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period, TRUE);

    foreach ($disaggregated_data as $metric_index => $metric_item) {
      $map['data']['attachment']['legend'][$metric_index] = $this->getMetricLabel($metric_index);
      if (empty($metric_item['locations'])) {
        continue;
      }
      foreach ($metric_item['locations'] as $location) {
        if (empty($map['data']['attachment']['locations'][$location['id']])) {
          $map['data']['attachment']['locations'][$location['id']] = [
            'attachment' => [],
            'latLng' => $location['map_data']['latLng'],
            'location_id' => $location['id'],
            'location_name' => $location['name'],
            'radius_factor' => 1,
            'radius_factors' => [
              'attachment' => 1,
            ],
            'admin_level' => $location['map_data']['admin_level'],
            'pcode' => $location['map_data']['pcode'],
          ];
        }
        $map['data']['attachment']['locations'][$location['id']]['attachment'][$metric_index] = $location['total'];
      }
    }

    // Prepare the modal contents.
    foreach ($map['data']['attachment']['locations'] as $location) {
      $location_id = $location['location_id'];
      $map['data']['attachment']['locations'][$location_id]['attachment'] = array_filter($location['attachment']);
      $map['data']['attachment']['modal_contents'][$location_id] = $this->prepareModalContentDonut($location, $map['data']['attachment']['legend'], $unit_group, $unit_label, $decimal_format);
    }

    // Add the measurments acrcoss different monitoring periods to be able to
    // create progress bar charts in the map modals.
    $location_variants = [];
    $measurements = $attachment->getMeasurements();
    if (!empty($measurements)) {
      foreach ($measurements as $measurement) {
        $reporting_period = $measurement->getReportingPeriodId();
        if (!array_key_exists($reporting_period, $reporting_periods)) {
          // If the measurements reporting period is not part of the
          // $reporting_periods array, then this measurement has not been
          // published yet, so we can safely skip it here.
          continue;
        }
        if (empty($measurement->disaggregated)) {
          continue;
        }
        $metric_count = count((array) $measurement->totals);
        $category_count = count((array) $measurement->disaggregated->categories);
        $data_matrix = $measurement->disaggregated->dataMatrix;
        $locations = $measurement->disaggregated->locations;

        // Filter out first item if it's completely empty, in which case this is
        // the country location, which has no disaggregated data anyways and
        // doesn't figure in the locations array.
        if (empty(array_filter($data_matrix[0]))) {
          array_shift($data_matrix);
        }

        foreach ($locations as $location_index => $location) {
          if (empty($map['data']['attachment']['modal_contents'][$location->id])) {
            continue;
          }

          if (empty($map['data']['attachment']['measurements'][$reporting_period])) {
            $map['data']['attachment']['measurements'][$reporting_period] = [
              'id' => $reporting_period,
              'reporting_period' => $reporting_periods_rendered[$reporting_period],
              'locations' => [],
            ];
          }
          foreach ($measurement->totals as $metric_index => $metric_item) {
            // Add information about measurement metrics, so that the frontend
            // can distinguish them.
            if ($attachment->isMeasurementField($metric_item->name->en) && !in_array($metric_index, $map['data']['attachment']['measurement_metrics'])) {
              $map['data']['attachment']['measurement_metrics'][] = $metric_index;
            }
            if (empty($map['data']['attachment']['measurements'][$reporting_period]['locations'][$location->id])) {
              $map['data']['attachment']['measurements'][$reporting_period]['locations'][$location->id] = [];
            }
            $data_matrix_location = $data_matrix[$location_index];
            $data_matrix_index = $metric_count * $category_count + $metric_index;
            $location_total = array_key_exists($data_matrix_index, $data_matrix_location) ? (int) $data_matrix_location[$data_matrix_index] : NULL;
            $map['data']['attachment']['measurements'][$reporting_period]['locations'][$location->id][$metric_index] = $location_total;
          }
        }
      }

      // If multiple monitoring periods are configured, we need to setup the
      // disaggregated data for each of them.
      if (count($configured_reporting_periods) > 1) {
        // It is imported to get the disaggregated data for the location
        // variants (reporting periods) without filtering out empty locations.
        // We need the same amount of locations as for the current result set,
        // otherwhise D3 will get confused, data binding will fail silently and
        // produce strange outputs in the map.
        $disaggregated_data_multiple_periods = $attachment->getDisaggregatedDataMultiple($configured_reporting_periods);
        if (!empty($disaggregated_data_multiple_periods)) {
          foreach ($disaggregated_data_multiple_periods as $period_data) {
            $reporting_period_id = $period_data['reporting_period']->id;
            $location_variants[$reporting_period_id] = [
              'locations' => [],
              'modal_contents' => [],
            ];
            // Get a shortcut to keep our code a bit easier to read.
            $period_locations = &$location_variants[$reporting_period_id]['locations'];

            foreach ($period_data['disaggregated_data'] as $metric_index => $metric_item) {
              if (!$attachment->isMeasurementField($metric_item['metric']->name->en)) {
                continue;
              }
              if (empty($metric_item['locations'])) {
                // No location data for this monitoring period.
                continue;
              }
              foreach ($metric_item['locations'] as $location) {
                $location_id = $location['id'];
                if (!array_key_exists($location_id, $map['data']['attachment']['locations'])) {
                  continue;
                }

                if (empty($period_locations[$location_id])) {
                  // Create a copy of the original location object, that
                  // contains all relevant information for the map rendering.
                  $period_locations[$location_id] = $map['data']['attachment']['locations'][$location_id];
                  foreach ($period_locations[$location_id]['attachment'] as $_index => $_value) {
                    if ($attachment->isMeasurementField($period_data['disaggregated_data'][$_index]['metric']->name->en)) {
                      $period_locations[$location_id]['attachment'][$_index] = NULL;
                    }
                  }
                }
                // Overwrite only the measurment metric for this location.
                $period_locations[$location_id]['attachment'][$metric_index] = $location['total'];
              }
            }

            $period_locations = array_values($period_locations);

            if (empty($period_locations)) {
              unset($location_variants[$reporting_period_id]);
            }
            else {
              // Prepare the modal contents.
              foreach ($period_locations as $location) {
                $location_id = $location['location_id'];
                $location_variants[$reporting_period_id]['modal_contents'][$location_id] = $this->prepareModalContentDonut($location, $map['data']['attachment']['legend'], $unit_group, $unit_label, $decimal_format);
              }
            }
          }

        }
      }
    }
    $map['data']['attachment']['locations'] = array_values($map['data']['attachment']['locations']);

    // Set the radius factors.
    $this->calulateDonutRadiusFactors($map['data']['attachment']['locations']);

    if (!empty($location_variants)) {
      // If we have location variants for measurement values, also do the
      // radius calculation.
      foreach ($location_variants as &$location_variant) {
        $this->calulateDonutRadiusFactors($location_variant['locations']);
      }
      // Add add them to the map data.
      $map['data']['attachment']['location_variants'] = $location_variants;
    }

    $map['settings']['map_style_config'] = $this->getDonutMapSettings();

    return $map;
  }

  /**
   * Get the settings for a donut map.
   *
   * @return array
   *   A settings array.
   */
  private function getDonutMapSettings() {
    $conf = $this->getBlockConfig()['map']['appearance'][self::STYLE_DONUT];

    $donut_whole_segments = array_filter(array_map(function ($item) {
      return (int) $item;
    }, $conf['whole_segments']));
    $donut_partial_segments = array_filter(array_map(function ($item) {
      return (int) $item;
    }, $conf['partial_segments']));
    $available_metric_items = array_keys($this->getDefaultAttachment()->getMetricFields());
    $reporting_periods = $this->getPlanReportingPeriods($this->getCurrentPlanId(), TRUE);
    $configured_monitoring_periods = is_object($conf['monitoring_period']) ? $conf['monitoring_period']->monitoring_period : $conf['monitoring_period'];

    $map_style_config = [
      'donut_whole_segments' => array_values(array_intersect($available_metric_items, $donut_whole_segments)),
      'donut_whole_segment_default' => (int) $conf['whole_segment_default'],
      'donut_partial_segments' => array_values(array_intersect($available_metric_items, $donut_partial_segments)),
      'donut_partial_segment_default' => (int) $conf['partial_segment_default'],
      'donut_monitoring_periods' => array_values(array_filter($configured_monitoring_periods, function ($item) use ($reporting_periods) {
        return $item != 'latest' && $item != 'none' && array_key_exists($item, $reporting_periods);
      })),
      'donut_display_value' => $conf['display_value'] ?? 'percentage',
    ];

    if (!in_array($map_style_config['donut_whole_segment_default'], $available_metric_items)) {
      $map_style_config['donut_whole_segment_default'] = reset($map_style_config['donut_whole_segments']);
    }
    // Check that the default segments are actually available. If not, default
    // to the first available segment.
    if (!in_array($map_style_config['donut_partial_segment_default'], $available_metric_items)) {
      $map_style_config['donut_partial_segment_default'] = reset($map_style_config['donut_partial_segments']);
    }

    return $map_style_config;
  }

  /**
   * Calculate radius factors for a set of locations.
   *
   * @param array $locations
   *   An array with locations to calculate the radius factor for.
   */
  private function calulateDonutRadiusFactors(array &$locations) {
    $max = 0;

    // First, get the maximum values across all locations.
    foreach ($locations as $location) {
      $base_value = (int) reset($location['attachment']);
      $max = $base_value > $max ? $base_value : $max;
    }

    // Then calculate a radius factor for each location, based on the maximum.
    foreach ($locations as &$location) {
      $base_value = (int) reset($location['attachment']);
      $radius_factor = $max > 0 ? 30 / $max * $base_value : 1;
      $radius_factor = $radius_factor > 1 ? $radius_factor : 1;
      $location['radius_factor'] = $radius_factor;
      $location['radius_factors']['attachment'] = $radius_factor;
      $location['total'] = $base_value;
    }
  }

  /**
   * Get the current reporting period for this element.
   *
   * @return object
   *   A reporting period object if found.
   */
  private function getCurrentReportingPeriod() {
    $plan_id = $this->getCurrentPlanId();
    $configured_reporting_periods = $this->getConfiguredReportingPeriods();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id);
    $reporting_period = reset($configured_reporting_periods);
    if ($reporting_period == 'latest' && !empty($reporting_periods)) {
      if ($latest_published_reporting_period = self::getLatestPublishedReportingPeriod($plan_id)) {
        $reporting_period = $latest_published_reporting_period;
      }
    }

    if ($reporting_period == 'none') {
      // Using the base metric totals instead of measurements identified by a
      // reporting period id.
      $reporting_period = FALSE;
    }
    return $reporting_period;
  }

  /**
   * Get the metric label for the given index.
   *
   * @param int $metric_index
   *   The index of the metric item in the attachments field list.
   *
   * @return string
   *   The label of the metric.
   */
  private function getMetricLabel($metric_index) {
    $conf = $this->getBlockConfig();
    $attachment = $this->getDefaultAttachment();
    $field = $attachment->getMetricFields()[$metric_index];
    $metric_label = $field;
    if (!empty($conf['map']['metric_labels']) && !empty($conf['map']['metric_labels'][$metric_index])) {
      $metric_label = $conf['map']['metric_labels'][$metric_index];
    }
    return $metric_label;
  }

  /**
   * Prepare the data for full metric item, that includes locations and modals.
   */
  private function prepareMetricItemMapData($metric_index, $metric_item, $decimal_format, $reporting_period = NULL) {
    $locations = $metric_item['locations'];

    // Foreach location, calculate a radius factor that will be used to draw
    // the map circles.
    $total_values = array_map(function ($item) {
      return (int) $item['total'];
    }, $metric_item['locations']);

    // Set the min and max weighing factors for the radius.
    $radius_factor_max = 40;
    $radius_factor_min = 1;

    $metric_label = $this->getMetricLabel($metric_index);

    $location_data = [];
    $modal_contents = [];

    foreach ($locations as $key => $location) {
      if (empty($location['map_data'])) {
        continue;
      }
      $location_data[$key] = $location['map_data'];
      $total_value = !empty($location['total']) ? (int) $location['total'] : 0;
      $radius_factor = $total_value > 0 ? ceil($radius_factor_max / max($total_values) * $total_value) : $radius_factor_min;
      $location_data[$key]['radius_factor'] = $radius_factor;

      $location['categories'] = array_filter($location['categories'], function ($category) {
        return $category['data'] !== NULL;
      });
      // The rendering is fully donw in the client, to save execution time on
      // plans with a huge number of locations.
      // See Drupal.hpc_map.planModalContent().
      $modal_contents[(string) $location['id']] = [
        'location_id' => $location['id'],
        'title' => $location['name'],
        'admin_level' => $location['map_data']['admin_level'],
        'pcode' => $location['map_data']['pcode'],
        'total' => $location['total'],
        'metric_label' => $metric_label,
        // The categories key is what makes this renderable in the client by
        // map.js.
        'categories' => array_map(function ($category) {
          return (object) [
            'name' => $category['name'],
            'value' => $category['data'],
          ];
        }, $location['categories']),

      ];
    }

    return [
      'location_data' => $location_data,
      'modal_contents' => $modal_contents,
      'monitoring_period' => $reporting_period && $metric_item['is_measurement'] ? ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $reporting_period,
        '#format_string' => 'Monitoring period #@period_number<br>@date_range',
      ], FALSE) : NULL,
    ];
  }

  /**
   * Prepare the content for a plan modal window showing disaggregated data.
   */
  private function prepareModalContentDonut($location, $legend, $unit_group, $unit_label, $decimal_format) {
    $modal_content = [
      'html' => '',
      'location_id' => $location['location_id'],
      'title' => $location['location_name'],
      'admin_level' => $location['admin_level'],
      'pcode' => $location['pcode'],
    ];

    $unit_defaults = [
      'amount' => [
        '#scale' => 'full',
      ],
    ];

    $items = [];

    // Add the group header.
    switch ($unit_group) {
      case 'people':
        $items[] = '<div class="section-header"><i class="material-icons group">group</i><span>' . $unit_label . '</span></div>';
        break;

      default:
        $items[] = '<div class="section-header"><i class="material-icons grain">grain</i><span>' . $unit_label . '</span></div>';
        break;
    }

    foreach ($legend as $metric_index => $metric_label) {
      $total_value = !empty($location['attachment'][$metric_index]) ? $location['attachment'][$metric_index] : FALSE;
      $content = '<div class="map-card-metric-wrapper" data-metric-index="' . $metric_index . '">';
      $content .= '  <div class="metric-label"><div class="metric-color-code"></div>' . $metric_label . '</div>';
      $content .= '  <div class="metric-value">' . CommonHelper::renderValue($total_value, 'value', 'hpc_autoformat_value', [
        'unit_type' => 'amount',
        'unit_defaults' => $unit_defaults,
        'decimal_format' => $decimal_format,
      ]) . '</div>';
      $content .= '</div>';
      $items[] = $content;
    }
    $modal_content['html'] = Markup::create(implode('', $items));

    return $modal_content;
  }

  /**
   * Get the reporting periods to show in the map.
   *
   * @return array
   *   An array of the configured reporting periods as either strings (latest,
   *   none) or ids.
   */
  private function getConfiguredReportingPeriods() {
    $conf = $this->getBlockConfig();
    $map_style = $conf['map']['appearance']['style'];
    $monitoring_periods = $conf['map']['appearance'][$map_style]['monitoring_period'];
    $monitoring_periods = is_object($monitoring_periods) ? $monitoring_periods->monitoring_period : $monitoring_periods;
    $configured_reporting_periods = array_filter($monitoring_periods);
    if (empty($configured_reporting_periods)) {
      return [];
    }
    $plan_id = $this->getCurrentPlanId();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id, TRUE);
    if (empty($reporting_periods)) {
      return [];
    }
    $latest = end($reporting_periods);
    $periods = [];
    foreach ($configured_reporting_periods as $period_id) {
      if ($period_id == 'latest') {
        $periods[$latest->id] = $latest->id;
        continue;
      }
      $periods[$period_id] = $period_id;
    }
    return $periods;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => NULL,
          ],
          'attachments' => [
            'filter' => [
              'entity_type' => NULL,
              'attachment_type' => NULL,
              'attachment_prototype' => NULL,
            ],
            'attachment_id' => NULL,
          ],
        ],
      ],
      'map' => [
        'appearance' => [
          'style' => self::STYLE_CIRCLE,
          self::STYLE_CIRCLE => [
            'monitoring_period' => ['latest' => 'latest'],
          ],
          self::STYLE_DONUT => [
            'whole_segments' => [],
            'whole_segment_default' => NULL,
            'partial_segments' => [],
            'partial_segment_default' => NULL,
            'monitoring_period' => ['latest' => 'latest'],
            'display_value' => NULL,
          ],
        ],
        'common' => [
          'default_attachment' => NULL,
          'disclaimer' => NULL,
          'pcodes_enabled' => FALSE,
          'comment' => NULL,
        ],
        'metric_labels' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    $conf = $this->getBlockConfig();
    if (empty($conf['attachments']['entity_attachments']['attachments']['attachment_id'])) {
      return $subform_key == 'attachments';
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (empty($conf['attachments']['entity_attachments']['attachments']['attachment_id'])) {
      return 'attachments';
    }
    return 'map';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'map';
  }

  /**
   * Form callback for the base settings form.
   */
  public function attachmentsForm(array $form, FormStateInterface $form_state) {
    $form['entity_attachments'] = [
      '#type' => 'entity_attachment_select',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'entity_attachments'),
      '#element_context' => $this->getBlockContext(),
      '#attachment_options' => [
        'attachment_prototypes' => TRUE,
      ],
      '#next_step' => 'map',
      '#container_wrapper' => $this->getContainerWrapper(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function mapForm(array $form, FormStateInterface $form_state) {
    $attachments = $this->getSelectedAttachments();
    $attachment = reset($attachments);
    $form['tabs'] = [
      '#type' => 'vertical_tabs',
    ];
    $form['appearance'] = [
      '#type' => 'details',
      '#title' => $this->t('Appearance'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    $form['appearance']['style'] = [
      '#type' => 'select',
      '#title' => $this->t('Map style'),
      '#description' => $this->t('Select which type of map will be used.'),
      '#options' => [
        self::STYLE_CIRCLE => $this->t('Circle'),
        self::STYLE_DONUT => $this->t('Donut'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        'style',
      ]) ?? self::STYLE_CIRCLE,
    ];

    $form['appearance'][self::STYLE_CIRCLE] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="map[appearance][style]"]' => ['value' => self::STYLE_CIRCLE],
        ],
      ],
    ];
    $form['appearance'][self::STYLE_CIRCLE]['monitoring_period'] = [
      '#type' => 'monitoring_periods',
      '#title' => $this->t('Monitoring period'),
      '#description' => $this->t('The monitoring period that should be used for data displayed in the map. If you select multiple monitoring periods, these will be made available as a drop-down on each measurement metric. Note that depending on the available data per attachment, some monitoring periods will be hidden if there is not enough data for a display in the map.'),
      '#plan_id' => $this->getCurrentPlanId(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_CIRCLE,
        'monitoring_period',
      ]),
      '#include_none' => TRUE,
    ];

    $form['appearance'][self::STYLE_DONUT] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="map[appearance][style]"]' => ['value' => self::STYLE_DONUT],
        ],
      ],
    ];
    $form['appearance'][self::STYLE_DONUT]['whole_segments'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Whole segments data points'),
      '#description' => $this->t('Select the goal metrics that are available for the whole segments.'),
      '#options' => $attachment->getGoalMetricFields(),
      '#default_value' => array_filter($this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'whole_segments',
      ])) ?? [array_key_first($attachment->getGoalMetricFields())],
      '#multiple' => TRUE,
    ];
    $form['appearance'][self::STYLE_DONUT]['whole_segment_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default data point for the full segment'),
      '#options' => $attachment->getGoalMetricFields(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'whole_segment_default',
      ]) ?? array_key_first($attachment->getGoalMetricFields()),
    ];
    $form['appearance'][self::STYLE_DONUT]['partial_segments'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Partial segments data points'),
      '#description' => $this->t('Select the goal or measurement metrics that are available for the partial segments.'),
      '#options' => $attachment->getMetricFields(),
      '#default_value' => array_filter($this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'partial_segments',
      ])) ?? [array_key_first($attachment->getMeasurementMetricFields())],
      '#multiple' => TRUE,
    ];
    $form['appearance'][self::STYLE_DONUT]['partial_segment_default'] = [
      '#type' => 'select',
      '#title' => $this->t('Default data point for the partial segment'),
      '#options' => $attachment->getMetricFields(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'partial_segment_default',
      ]) ?? array_key_first($attachment->getMeasurementMetricFields()),
    ];
    $form['appearance'][self::STYLE_DONUT]['monitoring_period'] = [
      '#type' => 'monitoring_periods',
      '#title' => $this->t('Monitoring periods'),
      '#plan_id' => $this->getCurrentPlanId(),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'monitoring_period',
      ]),
      '#default_all' => TRUE,
      '#include_latest' => TRUE,
      '#include_none' => TRUE,
    ];
    $form['appearance'][self::STYLE_DONUT]['display_value'] = [
      '#type' => 'select',
      '#title' => $this->t('Display value for the donut center'),
      '#description' => $this->t('Select the default value to display in the donut center. This can be changed interactively by the frontend user.'),
      '#options' => [
        self::DONUT_DISPLAY_VALUE_PERCENTAGE => $this->t('Proportion of partial vs. full segment'),
        self::DONUT_DISPLAY_VALUE_PARTIAL => $this->t('Absolute value of the partial segment'),
        self::DONUT_DISPLAY_VALUE_FULL => $this->t('Absolute value of the full segment'),
      ],
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'appearance',
        self::STYLE_DONUT,
        'display_value',
      ]),
    ];

    $form['common'] = [
      '#type' => 'details',
      '#title' => $this->t('Common'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];

    $attachment_options = array_map(function ($attachment) {
      return $attachment->getTitle();
    }, $attachments);
    $form['common']['default_attachment'] = [
      '#type' => 'select',
      '#title' => $this->t('Default attachment'),
      '#description' => $this->t('Please select the attachment that will show by default. If multiple attachments are available to this widget, then the user can select to see data for the other attachments by using a drop-down selector.'),
      '#options' => $attachment_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'common',
        'default_attachment',
      ]) ?? array_key_first($attachment_options),
      '#access' => count($attachments) > 1,
    ];

    $form['common']['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'common',
        'disclaimer',
      ]) ?? '',
    ];

    $form['common']['pcodes_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pcodes'),
      '#description' => $this->t('If checked, the map will list pcodes alongside location names and enable pcodes for the location filtering.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'common',
        'pcodes_enabled',
      ]) ?? FALSE,
    ];

    $form['common']['comment'] = $this->buildBlockCommentFormElement($this->getDefaultFormValueFromFormState($form_state, [
      'common',
      'comment',
    ]));

    // Allow element-wide override of metric item labels.
    $form['metric_labels'] = [
      '#type' => 'details',
      '#title' => $this->t('Metric labels'),
      '#tree' => TRUE,
      '#group' => 'tabs',
    ];
    foreach ($attachment->getMetricFields() as $metric_index => $metric_label) {
      $form['metric_labels'][$metric_index] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label for @type metrics', ['@type' => $metric_label]),
        '#description' => $this->t('You can override the label for this metric. Leave empty to use the default: <em>@default_label</em>.', [
          '@default_label' => $metric_label,
        ]),
        '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
          'metric_labels',
          $metric_index,
        ]),
      ];
    }

    return $form;
  }

  /**
   * Get the attachment prototype to use for the current block instance.
   *
   * @return object
   *   The attachment prototype object.
   */
  private function getAttachmentPrototype() {
    $prototypes = $this->getUniquePrototypes();
    return reset($prototypes);
  }

  /**
   * Get unique prototype options for the available attachments of this block.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  private function getUniquePrototypes() {
    $attachments = $this->getSelectedAttachments() ?? [];
    $prototype_opions = [];
    foreach ($attachments as $attachment) {
      $prototype = $attachment->prototype;
      if (array_key_exists($prototype->id, $prototype_opions)) {
        continue;
      }
      $prototype_opions[$prototype->id] = $prototype;
    }
    return $prototype_opions;
  }

  /**
   * Get the default attachment to show on initial widget rendering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment
   *   An attachment object.
   */
  private function getDefaultAttachment() {
    $default_attachment = &drupal_static(__FUNCTION__, NULL);
    if (!$default_attachment) {
      $conf = $this->getBlockConfig();
      $requested_attachment_id = $this->requestStack->getCurrentRequest()->request->get('attachment_id') ?? NULL;
      $default_attachment_id = $conf['map']['common']['default_attachment'] ?? NULL;
      $attachment_ids = $conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? [];
      $attachment_id = NULL;
      if ($requested_attachment_id && in_array($requested_attachment_id, $attachment_ids)) {
        $attachment_id = $requested_attachment_id;
      }
      elseif ($default_attachment_id && in_array($default_attachment_id, $attachment_ids)) {
        $attachment_id = $default_attachment_id;
      }
      elseif (count($attachment_ids)) {
        $attachment_id = reset($attachment_ids);
      }
      if (!$attachment_id) {
        return NULL;
      }
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query */
      $query = $this->getQueryHandler('attachment');
      $attachment = $query->getAttachment($attachment_id, TRUE);
      if (!$attachment || !$attachment instanceof DataAttachment) {
        return NULL;
      }
      if ($attachment->getPlanId() != $this->getCurrentPlanId()) {
        return NULL;
      }
      $default_attachment = $attachment;
    }
    return $default_attachment;
  }

  /**
   * Get all attachment objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachments[]
   *   An array of attachment objects, keyed by the attachment id.
   */
  private function getSelectedAttachments() {
    $conf = $this->getBlockConfig();
    $attachments = [];
    $attachment_ids = array_filter($conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? []);
    if (empty($attachment_ids)) {
      return $attachments;
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    foreach ($attachment_ids as $attachment_id) {
      $attachment = $query->getAttachment($attachment_id, TRUE);
      if (!$attachment) {
        continue;
      }
      $attachments[$attachment_id] = $attachment;
    }
    return $attachments;
  }

  /**
   * Get the attachment switcher.
   *
   * @return array|null
   *   A render array for the attachment switcher or NULL if not applicable.
   */
  private function getAttachmentSwitcher() {
    // Get the attachments.
    $attachments = $this->getSelectedAttachments();
    if (count($attachments) <= 1) {
      return NULL;
    }
    $attachment_options = array_map(function ($attachment) {
      return $attachment->getDescription();
    }, $attachments);
    $current_attachment = $this->getDefaultAttachment();
    return [
      '#type' => 'container',
      [
        '#theme' => 'ajax_switcher',
        '#element_key' => 'attachment_id',
        '#options' => $attachment_options,
        '#default_value' => $current_attachment?->id(),
        '#wrapper_id' => Html::getId('block-' . $this->getUuid()),
        '#plugin_id' => $this->getPluginId(),
        '#block_uuid' => $this->getUuid(),
        '#uri' => $this->getCurrentUri(),
      ],
    ];
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $this->getPageNode(),
      'attachment_prototype' => $this->getAttachmentPrototype(),
    ];
  }

}
