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
use Drupal\ghi_blocks\Interfaces\LazyMapDataFragmentBlockInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Map\MapDataFragment;
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
use Drupal\ghi_plans\ApiObjects\PlanReportingPeriod;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\DataPointConfigBackwardsCompatibilityTrait;
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
class PlanAttachmentMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface, ConfigValidationInterface, BlockCommentInterface, LazyMapBlockInterface, LazyMapDataFragmentBlockInterface {

  use AttachmentFilterTrait;
  use BlockCommentTrait;
  use ConfigValidationTrait;
  use ConfigurationPreviewMapTrait;
  use DataPointConfigBackwardsCompatibilityTrait;
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
        'location' => 'fabric_query:location',
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
    return !$this->getDefaultAttachment();
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
    if (!$attachment) {
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
    $attachment = $this->getDefaultAttachment(TRUE);
    if (!$attachment) {
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

    // The initial map payload is still compact: buildCircleMap() includes
    // metadata for all selectable tabs/variants and a full data slice only for
    // the default tab. Other tabs/variants are hydrated through slice_data_url.
    $map_settings = [
      'json' => $map['data'],
      'id' => $map_id,
      'settings_key' => 'plan_attachment_map',
      'disclaimer' => $conf['map']['common']['disclaimer'] ?: $this->getDefaultMapDisclaimer($this->getCurrentPlanObject()->getPlanLanguage()),
      'pcodes_enabled' => $conf['map']['common']['pcodes_enabled'] ?? TRUE,
      'label_min_zoom' => (int) ($conf['map']['common']['label_min_zoom'] ?? 6),
      'style' => $style,
      'outline_country' => $outline_country,
      'slice_data_url' => $this->getLazyMapFragmentUrl('ghi_blocks.map_data_fragment', $map_id, $attachment),
      'modal_data_url' => $this->getLazyMapFragmentUrl('ghi_blocks.map_modal_data', $map_id, $attachment),
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
   * {@inheritdoc}
   */
  public function buildLazyMapDataFragment(string $map_id, string $data_index, ?string $variant_id = NULL): ?MapDataFragment {
    $attachment = $this->getDefaultAttachment(TRUE);
    if (!$attachment) {
      return NULL;
    }

    // Rebuild the compact definitions so the request can resolve the selected
    // data index/variant without trusting client-supplied metric ids.
    $metadata = $this->buildCircleMapMetadata($attachment);
    $slice = $this->buildCircleMapDataSlice($attachment, $data_index, $variant_id, $metadata);
    if ($slice === NULL) {
      return NULL;
    }

    return new MapDataFragment($slice, $this->getMapFragmentCacheability($attachment));
  }

  /**
   * {@inheritdoc}
   */
  public function buildLazyMapModalFragment(string $map_id, string $data_index, string $object_id, ?string $variant_id = NULL): ?MapDataFragment {
    $attachment = $this->getDefaultAttachment(TRUE);
    if (!$attachment || !is_numeric($object_id)) {
      return NULL;
    }

    // Modal requests use the same definitions as data-slice requests, but
    // hydrate only the selected location's breakdown for the sidebar.
    $metadata = $this->buildCircleMapMetadata($attachment);
    $modal_content = $this->buildCircleMapModalContent($attachment, $data_index, $variant_id, (int) $object_id, $metadata);
    if ($modal_content === NULL) {
      return NULL;
    }

    return new MapDataFragment($modal_content, $this->getMapFragmentCacheability($attachment));
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
    $availability = $this->getMappableDataAvailability([$attachment]);
    return !empty($availability[$attachment->id()]);
  }

  /**
   * Check whether an attachment is a candidate for map rendering.
   *
   * This intentionally uses cheap attachment metadata only. Full
   * disaggregated-data loading happens when the lazy map payload is requested.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface $attachment
   *   The attachment to check.
   *
   * @return bool
   *   TRUE if the attachment can be considered for map rendering.
   */
  private function attachmentHasMapPotential(AttachmentInterface $attachment): bool {
    return $attachment instanceof Attachment
      && $attachment->canHaveDisaggregatedData()
      && $attachment->getPlanId() == $this->getCurrentPlanId();
  }

  /**
   * Map builder for circle maps.
   */
  private function buildCircleMap() {
    $attachment = $this->getDefaultAttachment();
    $map = [
      'data' => [],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => [],
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      'settings' => [],
    ];

    // Metadata is the lightweight map shape: labels, metrics, variant shells,
    // and internal definitions. It is cheap enough to compute once and then
    // reuse while hydrating the default slice below.
    $metadata = $this->buildCircleMapMetadata($attachment);
    $map['data'] = $metadata['data'];
    $map['settings'] = $metadata['settings'];
    if (empty($map['data'])) {
      return $map;
    }

    // The browser needs one complete slice to paint the initial map and to
    // size relative circle radii. Every other tab/variant remains a lazy shell.
    $default_data_index = array_key_first($map['data']);
    $default_slice = $this->buildCircleMapDataSlice($attachment, $default_data_index, NULL, $metadata);
    if (!empty($default_slice)) {
      $map['data'][$default_data_index] = array_replace($map['data'][$default_data_index], $default_slice);
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
   * Build compact metadata for all selectable circle-map tabs and variants.
   *
   * The returned array has two different audiences:
   * - data/settings are sent to the browser as lightweight tab/variant shells.
   * - definitions stay server-side and map opaque data indexes back to Fabric
   *   metric ids, measurement ids, and reporting periods for later fragments.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   *
   * @return array
   *   Map metadata and internal data definitions.
   */
  private function buildCircleMapMetadata(Attachment $attachment): array {
    $map = [
      'data' => [],
      'definitions' => [],
      'settings' => [
        'radius_scale_max' => 0,
      ],
    ];
    $plan_base_object = $attachment->getPlanObject();
    $plan_id = $plan_base_object->getSourceId();
    $reporting_periods = $this->getPlanReportingPeriods($plan_id);
    $current_reporting_period_id = $this->getCurrentReportingPeriod($plan_id);
    $configured_reporting_periods = $this->getConfiguredReportingPeriods($plan_id);
    $measurement_ids_by_period_id = $this->getMeasurementIdsByReportingPeriod($attachment, array_filter(array_unique(array_merge(
      $configured_reporting_periods,
      [$current_reporting_period_id],
    ))));

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $query */
    $query = $this->getQueryHandler('attachment');
    $summary = $query?->getMappableMapMetricSummary($attachment->id(), array_values($measurement_ids_by_period_id)) ?? [
      'base' => [],
      'measurements' => [],
      'max' => 0,
    ];
    $map['settings']['radius_scale_max'] = $summary['max'] ?? 0;

    $current_measurement_id = $current_reporting_period_id ? ($measurement_ids_by_period_id[$current_reporting_period_id] ?? NULL) : NULL;
    $current_metric_ids = array_unique(array_merge(
      array_keys($summary['base'] ?? []),
      array_keys($summary['measurements'][$current_measurement_id] ?? []),
    ));
    $metric_items = $this->buildCircleMapMetricItems($attachment, $current_metric_ids, $current_reporting_period_id, $current_measurement_id);
    foreach ($metric_items as $metric_item) {
      $map['data'][$metric_item['key']] = $metric_item['data'];
      $map['definitions'][$metric_item['key']] = $metric_item['definition'];
    }

    if (count($configured_reporting_periods) > 1) {
      $this->addCircleMapVariantMetadata($map, $attachment, $configured_reporting_periods, $reporting_periods, $measurement_ids_by_period_id, $summary);
    }
    return $map;
  }

  /**
   * Build compact metric entries for the given metric type ids.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param int[] $metric_type_ids
   *   Metric type ids.
   * @param int|null $reporting_period_id
   *   The selected reporting period id.
   * @param int|null $measurement_id
   *   The selected measurement id.
   *
   * @return array
   *   Metric entries.
   */
  private function buildCircleMapMetricItems(Attachment $attachment, array $metric_type_ids, ?int $reporting_period_id, ?int $measurement_id): array {
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $query */
    $query = $this->getQueryHandler('attachment');
    $plan_base_object = $attachment->getPlanObject();
    $items = [];
    foreach ($metric_type_ids as $metric_type_id) {
      $metric_type = $query?->getMetricType((int) $metric_type_id);
      if (!$metric_type instanceof MetricType) {
        continue;
      }
      $metric_index = $attachment->getPrototype()?->getOriginalIndexByMetricType($metric_type->getMachineName());
      if ($metric_index === NULL) {
        continue;
      }
      $metric_map_key = $metric_type->getMachineName() . '-' . $metric_index;
      $items[$metric_index] = [
        'key' => $metric_map_key,
        'data' => [
          'label' => $this->getMetricLabel($metric_type, $plan_base_object->getPlanLanguage()),
          'metric' => $this->buildMapMetric($metric_type),
          'unit_type' => $attachment->getUnitType(),
          'locations' => [],
          'variants' => [],
          'lazy' => TRUE,
          'slice_loaded' => FALSE,
        ],
        'definition' => [
          'metric_type_id' => (int) $metric_type_id,
          'metric_type' => $metric_type,
          'reporting_period_id' => $reporting_period_id,
          'measurement_id' => $measurement_id,
        ],
      ];
    }
    ksort($items);
    return $items;
  }

  /**
   * Add compact reporting-period variant metadata.
   *
   * @param array $map
   *   The map metadata.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param int[] $configured_reporting_periods
   *   Configured reporting period ids.
   * @param \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[] $reporting_periods
   *   Reporting period objects.
   * @param int[] $measurement_ids_by_period_id
   *   Measurement ids keyed by reporting period id.
   * @param array $summary
   *   Grouped metric summary data.
   */
  private function addCircleMapVariantMetadata(array &$map, Attachment $attachment, array $configured_reporting_periods, array $reporting_periods, array $measurement_ids_by_period_id, array $summary): void {
    foreach ($configured_reporting_periods as $reporting_period_id) {
      if (empty($reporting_periods[$reporting_period_id]) || empty($measurement_ids_by_period_id[$reporting_period_id])) {
        continue;
      }
      $measurement_id = $measurement_ids_by_period_id[$reporting_period_id];
      foreach ($summary['measurements'][$measurement_id] ?? [] as $metric_type_id => $metric_summary) {
        $metric_items = $this->buildCircleMapMetricItems($attachment, [(int) $metric_type_id], $reporting_period_id, $measurement_id);
        $metric_item = reset($metric_items);
        if (empty($metric_item) || empty($map['data'][$metric_item['key']])) {
          continue;
        }
        $metric = $metric_item['data']['metric'];
        if (!$attachment->isMeasurementField($metric->name->en)) {
          continue;
        }
        $map['data'][$metric_item['key']]['variants'][$reporting_period_id] = [
          'label' => $reporting_periods[$reporting_period_id]->format('Monitoring period #@period_number: @date_range'),
          'tab_label' => $reporting_periods[$reporting_period_id]->getPeriodNumber(),
          'locations' => [],
          'lazy' => TRUE,
          'slice_loaded' => FALSE,
        ];
        $map['definitions'][$metric_item['key']]['variants'][$reporting_period_id] = [
          'metric_type_id' => (int) $metric_type_id,
          'metric_type' => $metric_item['definition']['metric_type'],
          'reporting_period_id' => $reporting_period_id,
          'measurement_id' => $measurement_id,
        ];
      }
    }
  }

  /**
   * Build a data slice for one map tab or variant.
   *
   * A data slice is the map-facing payload for one selectable metric/period. It
   * contains locations, values, metric totals, and the loaded marker. It does
   * not contain modal/sidebar details; those are loaded per location by
   * buildCircleMapModalContent().
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param string $data_index
   *   The data index.
   * @param string|null $variant_id
   *   The selected variant id, if any.
   * @param array|null $metadata
   *   Optional circle map metadata.
   *
   * @return array|null
   *   The data slice, or NULL if the requested slice is invalid.
   */
  private function buildCircleMapDataSlice(Attachment $attachment, string $data_index, ?string $variant_id = NULL, ?array $metadata = NULL): ?array {
    $metadata = $metadata ?? $this->buildCircleMapMetadata($attachment);
    $definition = $this->getCircleMapDataDefinition($metadata, $data_index, $variant_id);
    if (!$definition) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $query */
    $query = $this->getQueryHandler('attachment');
    if (!$query) {
      return NULL;
    }

    // The query layer owns Fabric fact aggregation; the block only enriches
    // normalized totals with location geometry/search data and radius sizing.
    $totals_by_location_id = $query->getAttachmentMapLocationTotals($attachment->id(), $definition['metric_type_id'], $definition['measurement_id']);
    $locations = $this->buildMapLocationData($totals_by_location_id, $metadata['settings']['radius_scale_max'] ?? 0);

    return [
      'metric' => $this->buildMapMetric($definition['metric_type'], array_sum($totals_by_location_id)),
      'locations' => array_values($locations),
      'lazy' => TRUE,
      'slice_loaded' => TRUE,
    ];
  }

  /**
   * Build modal content for one map location.
   *
   * This is deliberately a single-location payload. Loading modal content only
   * when a sidebar is opened keeps large maps from holding every category
   * breakdown in the initial map data slice.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param string $data_index
   *   The data index.
   * @param string|null $variant_id
   *   The selected variant id, if any.
   * @param int $location_id
   *   The location id.
   * @param array|null $metadata
   *   Optional circle map metadata.
   *
   * @return array|null
   *   The modal content, or NULL if unavailable.
   */
  private function buildCircleMapModalContent(Attachment $attachment, string $data_index, ?string $variant_id, int $location_id, ?array $metadata = NULL): ?array {
    $metadata = $metadata ?? $this->buildCircleMapMetadata($attachment);
    $definition = $this->getCircleMapDataDefinition($metadata, $data_index, $variant_id);
    if (!$definition) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $query */
    $query = $this->getQueryHandler('attachment');
    /** @var \Drupal\ghi_base_objects\Plugin\FabricQuery\LocationQuery|null $location_query */
    $location_query = $this->getQueryHandler('location');
    if (!$query || !$location_query || !method_exists($location_query, 'getLocationsById')) {
      return NULL;
    }

    $locations = $location_query->getLocationsById([$location_id]);
    $location = $locations[$location_id] ?? NULL;
    if (!$location instanceof GeoJsonLocationInterface) {
      return NULL;
    }

    // Category detection and label resolution stay in the fact/query layer.
    $breakdown = $query->getAttachmentMapModalBreakdown($attachment->id(), $definition['metric_type_id'], $location_id, $definition['measurement_id']);
    $location_data = $location->getGeoJsonLocationData();
    $reporting_period = $this->getReportingPeriodForDefinition($attachment, $definition);
    $monitoring_period = $reporting_period && $attachment->isMeasurementField($definition['metric_type']->getName())
      ? $reporting_period->format('Monitoring period #@period_number<br>@date_range')
      : NULL;

    return [
      'object_id' => $location_id,
      'location_id' => $location_id,
      'title' => $location->getName(),
      'admin_level' => $location_data['admin_level'],
      'pcode' => $location_data['pcode'],
      'total' => $breakdown['total'] ?? 0,
      'metric_label' => $metadata['data'][$data_index]['label'],
      'monitoring_period' => $monitoring_period,
      'categories' => $breakdown['categories'] ?? [],
    ];
  }

  /**
   * Get the internal definition for a data slice.
   *
   * @param array $metadata
   *   Circle map metadata.
   * @param string $data_index
   *   The data index.
   * @param string|null $variant_id
   *   The selected variant id, if any.
   *
   * @return array|null
   *   The data definition, if available.
   */
  private function getCircleMapDataDefinition(array $metadata, string $data_index, ?string $variant_id = NULL): ?array {
    $definition = $metadata['definitions'][$data_index] ?? NULL;
    if (!$definition) {
      return NULL;
    }
    if ($variant_id !== NULL && $variant_id !== '') {
      return $definition['variants'][$variant_id] ?? NULL;
    }
    return $definition;
  }

  /**
   * Build cacheability metadata for map fragments.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The selected attachment.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cacheability metadata.
   */
  private function getMapFragmentCacheability(Attachment $attachment): CacheableMetadata {
    $cache_metadata = CacheableMetadata::createFromObject($attachment);
    $cache_metadata
      ->addCacheableDependency($this->getCurrentBaseObject())
      ->addCacheTags($this->getMapConfigCacheTags());
    return $cache_metadata;
  }

  /**
   * Build a route URL for a lazy map fragment endpoint.
   *
   * @param string $route_name
   *   The route name.
   * @param string $map_id
   *   The map element id.
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The selected attachment.
   *
   * @return string|null
   *   The fragment URL, if the block has a UUID.
   */
  private function getLazyMapFragmentUrl(string $route_name, string $map_id, Attachment $attachment): ?string {
    $block_uuid = $this->getUuid();
    if (!$block_uuid) {
      return NULL;
    }
    return Url::fromRoute($route_name, [
      'plugin_id' => $this->getPluginId(),
      'block_uuid' => $block_uuid,
    ], [
      'query' => [
        'current_uri' => $this->getCurrentUri(),
        'map_id' => $map_id,
        'attachment_id' => $attachment->id(),
      ],
    ])->toString();
  }

  /**
   * Build the compact metric object consumed by the map JS.
   *
   * @param \Drupal\hpc_api\ApiObjects\Types\MetricType $metric_type
   *   The metric type.
   * @param float|int $value
   *   The metric value.
   *
   * @return object
   *   The metric object.
   */
  private function buildMapMetric(MetricType $metric_type, float|int $value = 0): object {
    return (object) [
      'name' => (object) [
        'en' => $metric_type->getName(),
      ],
      'type' => $metric_type->getMachineName(),
      'value' => $value,
    ];
  }

  /**
   * Get measurement ids keyed by reporting period id.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param array $reporting_period_ids
   *   Reporting period ids.
   *
   * @return int[]
   *   Measurement ids keyed by reporting period id.
   */
  private function getMeasurementIdsByReportingPeriod(Attachment $attachment, array $reporting_period_ids): array {
    $measurement_ids = [];
    foreach ($reporting_period_ids as $reporting_period_id) {
      if (!$reporting_period_id || !is_numeric($reporting_period_id)) {
        continue;
      }
      $measurement = $attachment->getMeasurement((int) $reporting_period_id);
      if ($measurement) {
        $measurement_ids[(int) $reporting_period_id] = $measurement->id();
      }
    }
    return $measurement_ids;
  }

  /**
   * Build map location data for the given totals.
   *
   * @param float[] $totals_by_location_id
   *   Totals keyed by location id.
   * @param float|int $radius_scale_max
   *   The max value used for relative circle sizing.
   *
   * @return array
   *   Map location data keyed by location id.
   */
  private function buildMapLocationData(array $totals_by_location_id, float|int $radius_scale_max): array {
    if (empty($totals_by_location_id)) {
      return [];
    }
    $location_query = $this->getQueryHandler('location');
    if (!$location_query || !method_exists($location_query, 'getLocationsById')) {
      return [];
    }
    $locations = $location_query->getLocationsById(array_keys($totals_by_location_id));
    $location_data = [];
    foreach ($locations as $location_id => $location) {
      if (!$location instanceof GeoJsonLocationInterface || $location->getAdminLevel() == 0) {
        continue;
      }
      $total = $totals_by_location_id[$location_id] ?? 0;
      if ($total <= 0) {
        continue;
      }
      $item = $location->getGeoJsonLocationData() + [
        'object_id' => $location_id,
        'location_id' => $location_id,
        'location_name' => $location->getName(),
        'object_title' => $location->getName(),
      ];
      $item['total'] = $total;
      $item['radius_factor'] = $this->calculateRadiusFactor($total, $radius_scale_max);
      $location_data[$location_id] = $item;
    }
    return $location_data;
  }

  /**
   * Get the reporting period for a data definition.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment $attachment
   *   The attachment.
   * @param array $definition
   *   A map data definition.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod|null
   *   The reporting period, if any.
   */
  private function getReportingPeriodForDefinition(Attachment $attachment, array $definition): ?PlanReportingPeriod {
    $reporting_period_id = $definition['reporting_period_id'] ?? NULL;
    if (!$reporting_period_id) {
      return NULL;
    }
    $reporting_periods = $this->getPlanReportingPeriods($attachment->getPlanId());
    return $reporting_periods[$reporting_period_id] ?? NULL;
  }

  /**
   * Calculate the relative radius factor for one location value.
   *
   * @param float|int $value
   *   The location value.
   * @param float|int $max
   *   The global max value for this map.
   *
   * @return float|int
   *   The radius factor.
   */
  private function calculateRadiusFactor(float|int $value, float|int $max): float|int {
    $relative_size = ($max > 0 ? 10 / $max * $value : 1) * 4;
    return $relative_size > 1 ? $relative_size : 1;
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
   * @param bool $require_map_data
   *   Whether to require the selected attachment to have full map data.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment|null
   *   An attachment object, or NULL if none is available.
   */
  private function getDefaultAttachment(bool $require_map_data = FALSE) {
    $request = $this->requestStack->getCurrentRequest();
    $requested_attachment_id = $request->request->get('attachment_id')
      ?? $request->query->get('attachment_id')
      ?? NULL;
    $cache_key = implode(':', [
      $this->getUuid() . '::' . __METHOD__,
      (int) $require_map_data,
      $requested_attachment_id ?? '',
    ]);
    $default_attachment = &drupal_static($cache_key, NULL);
    if ($default_attachment === NULL) {
      $conf = $this->getBlockConfig();
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
      elseif ($require_map_data && !$this->attachmentCanBeMapped($attachment)) {
        $default_attachment = FALSE;
      }
    }
    return $default_attachment ?: NULL;
  }

  /**
   * Get all attachment objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects, keyed by the attachment id.
   */
  private function getSelectedAttachments() {
    $selected_attachments = &drupal_static($this->getUuid() . '::' . __METHOD__, NULL);
    if ($selected_attachments !== NULL) {
      return $selected_attachments;
    }

    $entities = $this->getConfiguredEntities();
    if (empty($entities)) {
      $selected_attachments = [];
      return [];
    }
    $attachments = $this->getConfiguredAttachments();
    $attachments = array_filter(
      $attachments,
      fn (AttachmentInterface $attachment) => $this->attachmentHasMapPotential($attachment),
    );
    if (empty($attachments)) {
      $selected_attachments = [];
      return [];
    }

    $availability = $this->getMappableDataAvailability($attachments);
    $selected_attachments = array_filter(
      $attachments,
      fn (Attachment $attachment) => !empty($availability[$attachment->id()]),
    );
    return $selected_attachments;
  }

  /**
   * Get mappable data availability for the given attachments.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[] $attachments
   *   The attachments to check.
   *
   * @return bool[]
   *   Availability keyed by attachment id.
   */
  private function getMappableDataAvailability(array $attachments): array {
    $attachment_ids = [];
    $measurement_ids_by_attachment_id = [];
    foreach ($attachments as $attachment) {
      if (!$attachment instanceof Attachment) {
        continue;
      }
      $attachment_id = $attachment->id();
      $attachment_ids[$attachment_id] = $attachment_id;
      $reporting_period = $this->getCurrentReportingPeriod($attachment->getPlanId());
      $measurement = $reporting_period ? $attachment->getMeasurement($reporting_period) : NULL;
      if ($measurement) {
        $measurement_ids_by_attachment_id[$attachment_id] = [$measurement->id()];
      }
    }
    if (empty($attachment_ids)) {
      return [];
    }

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery|null $query */
    $query = $this->getQueryHandler('attachment');
    return $query?->hasMappableDataMultiple($attachment_ids, $measurement_ids_by_attachment_id) ?? [];
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
