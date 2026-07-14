<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Helpers\AttachmentMatcher;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Map\MapPayload;
use Drupal\ghi_blocks\Plugin\Block\BlockCommentInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\BlockCommentTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_blocks\Traits\ConfigurationPreviewMapTrait;
use Drupal\ghi_blocks\Traits\GlobalMapTrait;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait;
use Drupal\ghi_plans\Traits\DisaggregatedDataTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_api\ApiObjects\Types\MetricType;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

/**
 * Provides a 'PlanAttachmentMap' block.
 */
#[Block(
  id: 'plan_attachment_map',
  admin_label: new TranslatableMarkup('Attachment Map'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ],
)]
class PlanAttachmentMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface, ConfigValidationInterface, BlockCommentInterface, LazyMapBlockInterface {

  use AttachmentFilterTrait;
  use BlockCommentTrait;
  use ConfigValidationTrait;
  use ConfigurationPreviewMapTrait;
  use DataPointConfigBackwardsCompatibilityTrait;
  use DisaggregatedDataTrait;
  use GlobalMapTrait;
  use PlanReportingPeriodTrait;

  const STYLE_CIRCLE = 'circle';

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      defaultTitle: 'Data by location',
      dataSources: [
        'attachment' => 'fabric_query:attachment',
        'country' => 'fabric_query:country',
        'entities' => 'fabric_query:entity',
      ],
      configForms: [
        'attachments' => [
          'title' => 'Attachments',
          'callback' => 'attachmentsForm',
        ],
        'map' => [
          'title' => 'Map',
          'callback' => 'mapForm',
          'base_form' => TRUE,
        ],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $attachment = $this->getDefaultAttachment();
    return (!$attachment || !$this->attachmentCanBeMapped($attachment));
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockComment(): ?string {
    $conf = $this->getBlockConfig();
    return $conf['map']['common']['comment'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadPngSelector(): ?string {
    $selector = parent::getDownloadPngSelector();
    return $selector ? $selector . '.map-image-loaded' : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $attachment = $this->getDefaultAttachment();
    if (!$attachment || !$this->attachmentCanBeMapped($attachment)) {
      // Nothing to show.
      return NULL;
    }

    $style = self::STYLE_CIRCLE;
    $chart_id = Html::getUniqueId('plan-attachment-map--' . $style);

    $attachment_switcher = $this->getAttachmentSwitcher();
    $block_uuid = $this->getUuid();
    $data_url_query = array_filter([
      'current_uri' => $this->getCurrentUri(),
      'map_id' => $chart_id,
      'attachment_id' => $attachment->id(),
    ], fn ($value) => $value !== NULL && $value !== '');
    $map_settings = [
      'id' => $chart_id,
      'data_url' => $block_uuid ? Url::fromRoute('ghi_blocks.map_data', [
        'plugin_id' => $this->getPluginId(),
        'block_uuid' => $block_uuid,
      ], [
        'query' => $data_url_query,
      ])->toString() : NULL,
    ];
    $map_tabs = NULL;
    $attachments = [
      'library' => ['ghi_blocks/map.gl.plan'],
      'drupalSettings' => [
        'plan_attachment_map' => [
          $chart_id => $map_settings,
        ],
      ],
    ];

    if ($this->isConfigurationPreview()) {
      // Block configuration preview needs the map data from the in-memory
      // block state. Reusing the lazy callback here would rebuild the saved
      // block from the page instead of the previewed configuration.
      $payload = $this->buildLazyMapPayload($chart_id);
      if (!$payload->isEmpty()) {
        // The lazy response normally replaces the tab markup over Ajax, so in
        // preview we copy those replacements into the initial render instead.
        $map_tabs = $payload->getHtml()['.pane-' . $chart_id . ' .map-tabs--inner'] ?? NULL;
        $attachments = BubbleableMetadata::mergeAttachments($attachments, $payload->getAttachments());
        $attachments['drupalSettings']['plan_attachment_map'][$chart_id] = $this->getConfigurationPreviewMap($payload->getMap());
      }
    }

    $build = [
      '#full_width' => FALSE,
    ];
    $build[] = [
      '#theme' => 'plan_attachment_map',
      '#chart_id' => $chart_id,
      '#map_tabs' => $map_tabs,
      '#map_type' => $style,
      '#attachment' => $attachment,
      '#attachment_switcher' => $attachment_switcher,
      '#legend' => $style == self::STYLE_CIRCLE ? FALSE : TRUE,
      '#attached' => $attachments,
    ];
    $cache_metadata = CacheableMetadata::createFromObject($attachment);
    $cache_metadata
      ->addCacheableDependency($this->getCurrentBaseObject())
      ->addCacheTags($this->getMapConfigCacheTags())
      ->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildLazyMapPayload(string $map_id): MapPayload {
    $attachment = $this->getDefaultAttachment();
    if (!$attachment || !$this->attachmentCanBeMapped($attachment)) {
      return MapPayload::forEmptyMap(
        [
          'id' => $map_id,
          'settings_key' => 'plan_attachment_map',
        ],
        MapPayload::cacheabilityFromTags(Cache::mergeTags($this->getCurrentBaseObject()->getCacheTags(), $this->getMapConfigCacheTags())),
      );
    }

    $conf = $this->getBlockConfig();
    $style = self::STYLE_CIRCLE;
    $map = $this->buildCircleMap();
    if (empty($map['data'])) {
      $cache_metadata = CacheableMetadata::createFromRenderArray($map);
      $cache_metadata
        ->addCacheableDependency($attachment)
        ->addCacheableDependency($this->getCurrentBaseObject())
        ->addCacheTags($this->getMapConfigCacheTags());
      return MapPayload::forEmptyMap(
        [
          'id' => $map_id,
          'settings_key' => 'plan_attachment_map',
        ],
        $cache_metadata,
      );
    }

    $outline_country = NULL;
    $focus_country = $this->getCurrentPlanObject()->getFocusCountry();
    if ($focus_country instanceof GeoJsonLocationInterface) {
      $outline_country = $focus_country->getGeoJsonLocationData();
      // @todo Remove BC layer here when done connecting fabric.
      $outline_country['location_id'] = $outline_country['id'];
      $outline_country['location_name'] = $outline_country['name'];
    }

    $map_settings = [
      'json' => $map['data'],
      'id' => $map_id,
      'settings_key' => 'plan_attachment_map',
      'disclaimer' => $conf['map']['common']['disclaimer'] ?: $this->getDefaultMapDisclaimer($this->getCurrentPlanObject()->getPlanLanguage()),
      'pcodes_enabled' => $conf['map']['common']['pcodes_enabled'] ?? TRUE,
      'label_min_zoom' => (int) ($conf['map']['common']['label_min_zoom'] ?? 6),
      'style' => $style,
      'outline_country' => $outline_country,
    ] + $map['settings'];

    $cache_metadata = CacheableMetadata::createFromRenderArray($map);
    $cache_metadata
      ->addCacheableDependency($this->getCurrentBaseObject())
      ->addCacheTags($this->getMapConfigCacheTags());

    return MapPayload::forMap(
      $map_settings,
      self::getGlobalMapSettings(),
      self::getMapboxConfig(),
      $cache_metadata,
      [
        '.pane-' . $map_id . ' .map-tabs--inner' => $map['tabs'],
      ],
    );
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
    if (!$attachment instanceof Attachment) {
      return FALSE;
    }
    if (!$attachment->canHaveDisaggregatedData()) {
      return FALSE;
    }
    $reporting_period = $this->getCurrentReportingPeriod($attachment->getPlanId());
    // canBeMapped() loads the full disaggregated dataset. A preceding
    // availability query would check the same underlying facts and then fetch
    // them again, so the full-data path is the planner source of truth here.
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
      return $reporting_period->format('Monitoring period #@period_number: @date_range');
    }, $reporting_periods);
    $reporting_period_id = $this->getCurrentReportingPeriod($plan_id);
    $configured_reporting_periods = $this->getConfiguredReportingPeriods($plan_id);

    $disaggregated_data = $this->transformDisaggregatedMapData($attachment->getDisaggregatedData($reporting_period_id), $attachment, TRUE);
    foreach ($disaggregated_data as $metric_index => $metric_item) {
      if ($attachment->metricItemIsEmpty($metric_item)) {
        continue;
      }
      /** @var \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_object */
      $metric_object = $metric_item['metric_object'];
      $metric_type = $metric_object->getMachineName();
      $metric_label = $this->getMetricLabel($metric_object, $plan_base_object->getPlanLanguage());
      $metric_map_key = $metric_type . '-' . $metric_index;
      $metric_map_data = $this->prepareMetricItemMapData($metric_label, $metric_item, $decimal_format, $reporting_period_id ? $reporting_periods[$reporting_period_id] : NULL);
      $map['data'][$metric_map_key] = [
        'label' => $metric_label,
        'metric' => $metric_item['metric'],
        'unit_type' => $metric_item['unit_type'],
        'locations' => array_values($metric_map_data['location_data']),
        'modal_contents' => $metric_map_data['modal_contents'],
        'variants' => [],
      ];
      CacheableMetadata::createFromObject($attachment)->applyTo($map);
    }

    if (empty($map['data'])) {
      // No data, no widget.
      return $map;
    }

    // If more than one monitoring periods have been selected, add a a variant
    // drop-down.
    if (count($configured_reporting_periods) > 1) {
      $disaggregated_data_multiple_periods = $attachment->getDisaggregatedDataMultiple($configured_reporting_periods);
      if (!empty($disaggregated_data_multiple_periods)) {
        foreach ($disaggregated_data_multiple_periods as $period_data) {
          /** @var \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod $reporting_period */
          $reporting_period = $period_data['reporting_period'];
          foreach ($period_data['disaggregated_data'] as $metric_index => $metric_item) {
            /** @var \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_object */
            $metric_object = $metric_item['metric_object'];
            $metric_type = $metric_object->getMachineName();
            $metric_label = $this->getMetricLabel($metric_object, $plan_base_object->getPlanLanguage());
            $metric_map_key = $metric_type . '-' . $metric_index;
            if (empty($map['data'][$metric_map_key])) {
              continue;
            }
            if ($attachment->metricItemIsEmpty($metric_item)) {
              continue;
            }
            if (!empty($map['data'][$metric_map_key]['variants'][$reporting_period->id()])) {
              continue;
            }
            if (!$attachment->isMeasurementField($metric_item['metric']->name->en)) {
              continue;
            }
            $metric_map_data = $this->prepareMetricItemMapData($metric_label, $metric_item, $decimal_format, $reporting_period);
            $map['data'][$metric_map_key]['variants'][$reporting_period->id()] = [
              'label' => $reporting_periods_rendered[$reporting_period->id()],
              'tab_label' => $reporting_period->getPeriodNumber(),
              'locations' => $metric_map_data['location_data'],
              'modal_contents' => $metric_map_data['modal_contents'],
            ];
            CacheableMetadata::createFromObject($attachment)->applyTo($map);
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
        // Otherwise just display a tab link.
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
   * @param int $plan_id
   *   The plan id for which to retrieve the current reporting period.
   *
   * @return int|null
   *   A reporting period id if found.
   */
  private function getCurrentReportingPeriod(int $plan_id) {
    $configured_reporting_periods = $this->getConfiguredReportingPeriods($plan_id);
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
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_object
   *   The metric type object.
   * @param string $langcode
   *   The language code to use for translations.
   *
   * @return string
   *   The label of the metric.
   */
  private function getMetricLabel(MetricType $metric_object, string $langcode = 'en') {
    $conf = $this->getBlockConfig();

    $attachments = $this->getSelectedAttachments();
    $attachment = !empty($attachments) ? reset($attachments) : NULL;

    // Backwards compatible change for overridden metric labels, which use
    // metric types instead of metric indexes now.
    if (!empty($conf['map']['metric_labels']) && $prototype = $attachment?->getPrototype()) {
      foreach ($conf['map']['metric_labels'] as $metric_index => $metric_label) {
        if (!is_numeric($metric_index)) {
          continue;
        }
        unset($conf['map']['metric_labels'][$metric_index]);
        $metric_type = $this->getMetricTypeByIndex($metric_index, $prototype);
        if ($metric_type) {
          $conf['map']['metric_labels'][$metric_type] = $metric_label;
        }
      }
    }

    $metric_label = NULL;
    $metric_type = $metric_object->getMachineName();
    if ($metric_type && !empty($conf['map']['metric_labels'][$metric_type])) {
      $metric_label = $conf['map']['metric_labels'][$metric_type];
    }
    return $metric_label ?: $metric_object->getLabel($langcode);
  }

  /**
   * Prepare the data for full metric item, that includes locations and modals.
   */
  private function prepareMetricItemMapData($metric_label, $metric_item, $decimal_format, $reporting_period = NULL) {
    $locations = $metric_item['locations'];

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
      'monitoring_period' => $reporting_period && $metric_item['is_measurement'] ? $reporting_period->format('Monitoring period #@period_number<br>@date_range') : NULL,
    ];
  }

  /**
   * Get the reporting periods to show in the map.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return array
   *   An array of the configured reporting periods as either strings (latest,
   *   none) or ids.
   */
  private function getConfiguredReportingPeriods(int $plan_id) {
    $conf = $this->getBlockConfig();
    $style = self::STYLE_CIRCLE;
    $monitoring_periods = $conf['map']['appearance'][$style]['monitoring_period'];
    $monitoring_periods = is_object($monitoring_periods) ? $monitoring_periods->monitoring_period : $monitoring_periods;
    $configured_reporting_periods = array_filter($monitoring_periods);
    if (empty($configured_reporting_periods)) {
      return [];
    }
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
          'label_min_zoom' => 6,
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
      '#title' => $this->t('Data'),
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
      '#title' => $this->t('Map features'),
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

    $form['common']['label_min_zoom'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum zoom for labels'),
      '#description' => $this->t('Specifiy at which zoom level the admin area labels become visible. Setting this to <em>0</em> will show them at any zoom level. Default is <em>6</em>.'),
      '#min' => 0,
      '#max' => 9,
      // With the following, the number validation fails for some obscure
      // reason.
      '#step' => 'any',
      '#default_value' => (int) $this->getDefaultFormValueFromFormState($form_state, [
        'common',
        'label_min_zoom',
      ]) ?? 0,
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
    foreach ($attachment->getFields() as $metric_type => $metric_label) {
      $form['metric_labels'][$metric_type] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label for metric @type', ['@type' => $metric_label]),
        '#description' => $this->t('You can override the label for this metric. Leave empty to use the default: <em>@default_label</em>.', [
          '@default_label' => $metric_label,
        ]),
        '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
          'metric_labels',
          $metric_type,
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
      $prototype = $attachment->getPrototype();
      if (array_key_exists($prototype->id(), $prototype_opions)) {
        continue;
      }
      $prototype_opions[$prototype->id()] = $prototype;
    }
    return $prototype_opions;
  }

  /**
   * Get the default attachment to show on initial widget rendering.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment
   *   An attachment object.
   */
  private function getDefaultAttachment() {
    $default_attachment = &drupal_static($this->getUuid() . '::' . __METHOD__, NULL);
    if ($default_attachment === NULL) {
      $conf = $this->getBlockConfig();
      $request = $this->requestStack->getCurrentRequest();
      $requested_attachment_id = $request->request->get('attachment_id') ?? $request->query->get('attachment_id') ?? NULL;
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
      if (!$attachment instanceof Attachment) {
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
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
   * {@inheritdoc}
   */
  public function getBlockConfig() {
    $config = parent::getBlockConfig();
    if (empty($config['map']['appearance']['circle']['monitoring_period'])) {
      return $config;
    }
    // There is a very limited set of plans that has an object as the default
    // value. We correct that here.
    if (is_object($config['map']['appearance']['circle']['monitoring_period'])) {
      $config['map']['appearance']['circle']['monitoring_period'] = $config['map']['appearance']['circle']['monitoring_period']->monitoring_period;
    }
    return $config;
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
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    $plan_id = $plan_object->getSourceId();

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\Plan $query_handler */
    $query_handler = $this->fabricQueryManager->createInstance('plan');
    $plan_entities = [
      $plan_id => $query_handler->getPlan($plan_id),
    ];

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->getQueryHandler('entities');
    $plan_entities += $query->getEntitiesForPlan($plan_id, $this->getCurrentBaseObject()) ?? [];
    return $plan_entities;
  }

  /**
   * Get the configured attachment ids if any.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects.
   */
  private function getConfiguredAttachments() {
    $conf = $this->getBlockConfig();
    $attachment_ids = array_filter($conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? []);
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    return !empty($attachment_ids) ? $query->getAttachmentsById($attachment_ids) : [];
  }

  /**
   * Get the available attachments.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects.
   */
  private function getAvailableAttachments() {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query_handler */
    $query_handler = $this->getQueryHandler('attachment');
    return $query_handler->getAttachmentsForPlan($plan_object->getSourceId(), $this->getCurrentBaseObject(), [
      'Caseload',
      'Indicator',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors() {
    $errors = [];
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      if (!$this->getCurrentBaseEntity() instanceof SectionNodeInterface && !$this->getCurrentBaseEntity() instanceof SubpageNodeInterface) {
        $errors[] = $this->t('No plan object available on the target page. Check if the necessary data objects have been added.');
      }
      else {
        $errors[] = $this->t('No plan object available on the target page.');
      }
      return $errors;
    }
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
          if (!$attachment instanceof Attachment) {
            continue;
          }
          $filtered_attachments = AttachmentMatcher::matchAttachments($attachment, $available_attachments);
          foreach ($filtered_attachments as $filtered_attachment) {
            $conf['attachments']['entity_attachments']['attachments']['attachment_id'][$filtered_attachment->id()] = $filtered_attachment->id();
            $conf['attachments']['entity_attachments']['entities']['entity_ids'][$filtered_attachment->getSourceEntityId()] = $filtered_attachment->getSourceEntityId();
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

    // Update the selected monitoring periods if necessary.
    if (empty($configured_attachments) || reset($configured_attachments)->getPlanId() != $this->getCurrentPlanId()) {
      $conf['map']['appearance']['circle']['monitoring_period'] = ['latest' => 'latest'];
    }

    $this->setBlockConfig($conf);
  }

}
