<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Helpers\AttachmentMatcher;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_blocks\Traits\GlobalMapTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
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
class PlanAttachmentMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface, ConfigValidationInterface {

  use PlanReportingPeriodTrait;
  use BlockCommentTrait;
  use GlobalMapTrait;
  use ConfigValidationTrait;
  use AttachmentFilterTrait;

  const STYLE_CIRCLE = 'circle';

  const DEFAULT_DISCLAIMER = 'The boundaries and names shown and the designations used on this map do not imply official endorsement or acceptance by the United Nations.';

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $attachment = $this->getDefaultAttachment();
    if (!$attachment || !$this->attachmentCanBeMapped($attachment)) {
      // Nothing to show.
      return NULL;
    }

    $conf = $this->getBlockConfig();
    $style = self::STYLE_CIRCLE;
    $chart_id = Html::getUniqueId('plan-attachment-map--' . $style);
    $map = $this->buildCircleMap();

    if (empty($map['data'])) {
      // Nothing to show.
      return NULL;
    }
    $map_settings = [
      // If the map data is empty, it is important to set it to NULL, otherwise
      // the empty array is simply ignored due to the way that Drupal merges the
      // given settings into the existing ones.
      'json' => !empty($map['data']) ? $map['data'] : NULL,
      'id' => $chart_id,
      'disclaimer' => $conf['map']['common']['disclaimer'] ?? self::DEFAULT_DISCLAIMER,
      'pcodes_enabled' => $conf['map']['common']['pcodes_enabled'] ?? TRUE,
      'style' => $style,
    ] + $map['settings'];

    $attachment_switcher = $this->getAttachmentSwitcher();

    $build = [
      '#full_width' => FALSE,
    ];
    $build[] = [
      '#theme' => 'plan_attachment_map',
      '#chart_id' => $chart_id,
      '#map_tabs' => $map['tabs'] ?? NULL,
      '#map_type' => $style,
      '#attachment_switcher' => $attachment_switcher,
      '#legend' => $style == self::STYLE_CIRCLE ? FALSE : TRUE,
      '#attached' => [
        'library' => ['ghi_blocks/map.gl.plan'],
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
   * Check if the given attachment can be mapped.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment
   *   The attachment to check.
   *
   * @return bool
   *   TRUE if the given attachment can be mapped, FALSE otherwise.
   */
  private function attachmentCanBeMapped(AttachmentInterface $attachment) {
    if (!$attachment instanceof DataAttachment) {
      return FALSE;
    }
    if (!$attachment->hasDisaggregatedData()) {
      return FALSE;
    }
    $reporting_period = $this->getCurrentReportingPeriod();
    return $attachment->canBeMapped($reporting_period);
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
    $plan_base_object = $attachment->getPlanObject();
    $plan_id = $plan_base_object->getSourceId();
    $decimal_format = $plan_base_object->getDecimalFormat();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id);
    $reporting_periods_rendered = array_map(function ($reporting_period) {
      return ThemeHelper::render([
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $reporting_period,
        '#format_string' => 'Monitoring period #@period_number: @date_range',
      ]);
    }, $reporting_periods);
    $reporting_period_id = $this->getCurrentReportingPeriod();
    $configured_reporting_periods = $this->getConfiguredReportingPeriods();

    $disaggregated_data = $attachment->getDisaggregatedData($reporting_period_id, TRUE);
    foreach ($disaggregated_data as $metric_index => $metric_item) {
      if (empty($metric_item['locations'])) {
        continue;
      }
      $metric_label = $this->getMetricLabel($metric_index);
      $metric_type = strtolower($metric_item['metric']->type);
      $metric_map_key = $metric_type . '-' . $metric_index;
      $metric_map_data = $this->prepareMetricItemMapData($metric_index, $metric_item, $decimal_format, $reporting_period_id ? $reporting_periods[$reporting_period_id] : NULL);
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
        foreach ($disaggregated_data_multiple_periods as $period_data) {
          /** @var \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod $reporting_period */
          $reporting_period = $period_data['reporting_period'];
          foreach ($period_data['disaggregated_data'] as $metric_index => $metric_item) {
            $metric_type = strtolower($metric_item['metric']->type);
            $metric_map_key = $metric_type . '-' . $metric_index;
            if (empty($map['data'][$metric_map_key])) {
              continue;
            }
            if (empty($metric_item['locations'])) {
              continue;
            }
            if (!empty($map['data'][$metric_map_key]['variants'][$reporting_period->id()])) {
              continue;
            }
            if (!$attachment->isMeasurementField($metric_item['metric']->name->en)) {
              continue;
            }
            $metric_map_data = $this->prepareMetricItemMapData($metric_index, $metric_item, $decimal_format, $reporting_period);
            $map['data'][$metric_map_key]['variants'][$reporting_period->id()] = [
              'label' => $reporting_periods_rendered[$reporting_period->id()],
              'tab_label' => $reporting_period->getPeriodNumber(),
              'locations' => $metric_map_data['location_data'],
              'modal_contents' => $metric_map_data['modal_contents'],
            ];
          }
        }
      }
    }

    // Calculate the grouped sizes, so that the circle sizes are relative to a
    // common max value on all available map tabs.
    $this->calculateGroupedSizes($map['data']);

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
              'href' => '#',
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
              'href' => '#',
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
   * Calculate the grouped size of each location item based.
   *
   * @param array $data
   *   A map data array with tab data keyed by the tab key.
   */
  private function calculateGroupedSizes(&$data) {
    $ranges = ['min' => 0, 'max' => 0];
    foreach ($data as $tab_data) {
      $tab_min = array_reduce($tab_data['locations'], function ($carry, $item) {
        $value = is_numeric($item['total']) ? $item['total'] : 0;
        return $carry > $value ? $value : $carry;
      }, 0);
      $tab_max = array_reduce($tab_data['locations'], function ($carry, $item) {
        $value = is_numeric($item['total']) ? $item['total'] : 0;
        return $carry < $value ? $value : $carry;
      }, 0);

      $ranges['min'] = min($ranges['min'], $tab_min);
      $ranges['max'] = max($ranges['max'], $tab_max);
    }

    foreach ($data as &$item) {
      foreach ($item['locations'] as &$location) {
        $max = $ranges['max'];
        $relative_size = ($max > 0 ? 10 / $max * $location['total'] : 1) * 4;
        $location['radius_factor'] = $relative_size > 1 ? $relative_size : 1;
      }
    }
  }

  /**
   * Get the current reporting period for this element.
   *
   * @return int|null
   *   A reporting period id if found.
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
      $reporting_period = NULL;
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
    $metric_label = $this->getMetricLabel($metric_index);

    $location_data = [];
    $modal_contents = [];

    foreach ($locations as $key => $location) {
      if (empty($location['map_data'])) {
        continue;
      }
      $location_data[$key] = $location['map_data'];

      $location['categories'] = array_filter($location['categories'], function ($category) {
        return $category['data'] !== NULL;
      });
      // The rendering is fully done in the client, to save execution time on
      // plans with a huge number of locations.
      // See Drupal.hpc_map.planModalContent().
      $modal_contents[(string) $location['id']] = [
        'object_id' => $location['id'],
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
   * Get the reporting periods to show in the map.
   *
   * @return array
   *   An array of the configured reporting periods as either strings (latest,
   *   none) or ids.
   */
  private function getConfiguredReportingPeriods() {
    $conf = $this->getBlockConfig();
    $style = self::STYLE_CIRCLE;
    $monitoring_periods = $conf['map']['appearance'][$style]['monitoring_period'];
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
        $periods[$latest->id()] = $latest->id();
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
    if (empty($this->getSelectedAttachments())) {
      return $subform_key == 'attachments';
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    if (empty($this->getSelectedAttachments())) {
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
      '#disagg_warning' => TRUE,
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

    $form['appearance'][self::STYLE_CIRCLE] = [
      '#type' => 'container',
      '#tree' => TRUE,
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
    if ($default_attachment === NULL) {
      $conf = $this->getBlockConfig();
      $requested_attachment_id = $this->requestStack->getCurrentRequest()->request->get('attachment_id') ?? NULL;
      $default_attachment_id = $conf['map']['common']['default_attachment'] ?? NULL;
      $attachments = $this->getSelectedAttachments();
      $attachment = NULL;
      if ($requested_attachment_id && !empty($attachments[$requested_attachment_id])) {
        $attachment = $attachments[$requested_attachment_id];
      }
      elseif ($default_attachment_id && !empty($attachments[$default_attachment_id])) {
        $attachment = $attachments[$default_attachment_id];
      }
      elseif (count($attachments)) {
        $attachment = reset($attachments);
      }
      $default_attachment = $attachment;
      if (!$attachment instanceof DataAttachment) {
        $default_attachment = FALSE;
      }
      elseif (!$this->attachmentCanBeMapped($attachment)) {
        $default_attachment = FALSE;
      }
      elseif ($attachment->getPlanId() != $this->getCurrentPlanId()) {
        $default_attachment = FALSE;
      }
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
    $entities = $this->getConfiguredEntities();
    if (empty($entities)) {
      return [];
    }
    $attachments = $this->getConfiguredAttachments();
    $attachments = array_filter($attachments, function (AttachmentInterface $attachment) {
      return $this->attachmentCanBeMapped($attachment);
    });
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
    $page_node = $this->getPageNode();
    return [
      'page_node' => $page_node,
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $page_node,
      'attachment_prototype' => $this->getAttachmentPrototype(),
    ];
  }

  /**
   * Get the configured entity ids if any.
   *
   * @return array
   *   An array of entity ids.
   */
  private function getConfiguredEntities() {
    $conf = $this->getBlockConfig();
    return array_filter($conf['attachments']['entity_attachments']['entities']['entity_ids'] ?? []);
  }

  /**
   * Get the available entities.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of plan entity objects.
   */
  private function getAvailableEntities() {
    $plan_id = $this->getCurrentPlanObject()->getSourceId();

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanBasicQuery $query_handler */
    $query_handler = $this->endpointQueryManager->createInstance('plan_basic_query');
    $plan_entities = [
      $plan_id => $query_handler->getBaseData($plan_id),
    ];

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query_handler */
    $query_handler = $this->endpointQueryManager->createInstance('plan_entities_query');
    $query_handler->setPlaceholder('plan_id', $plan_id);
    $plan_entities += $query_handler->getPlanEntities($this->getCurrentBaseObject()) ?? [];
    return $plan_entities;
  }

  /**
   * Get the configured attachment ids if any.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of attachment objects.
   */
  private function getConfiguredAttachments() {
    $conf = $this->getBlockConfig();
    $attachment_ids = array_filter($conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? []);
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->getQueryHandler('attachment_search');
    return !empty($attachment_ids) ? $query->getAttachmentsById($attachment_ids) : [];
  }

  /**
   * Get the available attachments.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of attachment objects.
   */
  private function getAvailableAttachments() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query_handler */
    $query_handler = $this->endpointQueryManager->createInstance('plan_entities_query');
    $query_handler->setPlaceholder('plan_id', $this->getCurrentPlanObject()->getSourceId());
    return $query_handler->getDataAttachments($this->getCurrentBaseObject());
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors() {
    $errors = [];
    $configured_entities = $this->getConfiguredEntities();
    if (!empty($configured_entities)) {
      $available_entities = $this->getAvailableEntities();
      if (!empty($configured_entities) && $available_entities && count($configured_entities) != count(array_intersect_key($configured_entities, $available_entities))) {
        $errors[] = $this->t('Some configured entities are not available');
      }
    }
    $configured_attachments = $this->getConfiguredAttachments();
    if (!empty($configured_attachments)) {
      $available_attachments = $this->getAvailableAttachments();
      if (!empty($configured_attachments) && $available_attachments && count($configured_attachments) != count(array_intersect_key($configured_attachments, $available_attachments))) {
        $errors[] = $this->t('Some configured attachments are not available');
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigErrors() {
    $conf = $this->getBlockConfig();

    $configured_entities = $this->getConfiguredEntities();
    $available_entities = $this->getAvailableEntities();
    $valid_entity_ids = array_intersect_key($configured_entities, $available_entities);
    if (!empty($configured_entities) && !empty($valid_entity_ids)) {
      $conf['attachments']['entity_attachments']['entities']['entity_ids'] = array_combine($valid_entity_ids, $valid_entity_ids);
    }
    else {
      $conf['attachments']['entity_attachments']['entities']['entity_ids'] = array_fill_keys(array_keys($available_entities), 0);
    }

    $configured_attachments = $this->getConfiguredAttachments();
    $available_attachments = $this->getAvailableAttachments();

    if (!empty($configured_attachments)) {
      // Less probable, but maybe one of the configured attachments is still
      // valid in the new context.
      $valid_attachment = array_intersect_key($configured_attachments, $available_attachments);
      $valid_attachment_ids = !empty($valid_attachment) ? array_keys($valid_attachment) : [];
      $conf['attachments']['entity_attachments']['attachments']['attachment_id'] = [];
      if (!empty($valid_attachment_ids)) {
        // If so, let's use these.
        $conf['attachments']['entity_attachments']['attachments']['attachment_id'] = array_combine($valid_attachment_ids, $valid_attachment_ids);
      }
      else {
        // Otherwise, go over all configured attachments (valid in the original
        // context) and see if we can find comparable attachments in the new
        // context via $available_attachments.
        foreach ($configured_attachments as $attachment) {
          if (!$attachment instanceof DataAttachment) {
            continue;
          }
          $filtered_attachments = AttachmentMatcher::matchDataAttachments($attachment, $available_attachments);
          foreach ($filtered_attachments as $filtered_attachment) {
            $conf['attachments']['entity_attachments']['attachments']['attachment_id'][$filtered_attachment->id()] = $filtered_attachment->id();
            $conf['attachments']['entity_attachments']['entities']['entity_ids'][$filtered_attachment->source->entity_id] = $filtered_attachment->source->entity_id;
          }
        }
      }
    }

    // Check the configured default attachment.
    $default_attachment = $conf['map']['common']['default_attachment'] ?? NULL;
    $attachment_ids = $conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? [];
    if ($default_attachment && !array_key_exists($default_attachment, $attachment_ids)) {
      // Just unset the default attachment, so that the rendering can decide
      // which one to use.
      $conf['map']['common']['default_attachment'] = NULL;
    }

    $this->setBlockConfig($conf);
  }

}
