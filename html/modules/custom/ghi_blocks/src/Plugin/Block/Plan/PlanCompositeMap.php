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
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Attachments\AttachmentInterface;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\ghi_plans\Traits\PlanReportingPeriodTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_geojson\GeoJsonLocationInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

/**
 * Provides a composite map block for plan pages.
 */
#[Block(
  id: 'plan_composite_map',
  admin_label: new TranslatableMarkup('Composite Map'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ],
)]
class PlanCompositeMap extends GHIBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadPNGInterface, ConfigValidationInterface, BlockCommentInterface, LazyMapBlockInterface {

  use AttachmentFilterTrait;
  use BlockCommentTrait;
  use ConfigValidationTrait;
  use ConfigurationContainerTrait;
  use ConfigurationPreviewMapTrait;
  use GlobalMapTrait;
  use PlanReportingPeriodTrait;

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
        'maps' => [
          'title' => 'Maps',
          'callback' => 'mapsForm',
        ],
        'common' => [
          'title' => 'Common',
          'callback' => 'commonForm',
          'base_form' => TRUE,
        ],
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $conf = $this->getBlockConfig();
    return empty($conf['maps']['maps']) || empty($this->getConfiguredAttachmentIds());
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockComment(): ?string {
    $conf = $this->getBlockConfig();
    return $conf['common']['comment'] ?? NULL;
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
    $conf = $this->getBlockConfig();
    if (empty($conf['maps']['maps']) || empty($this->getSelectedAttachments())) {
      return NULL;
    }

    $chart_id = Html::getUniqueId('plan-composite-map');
    $block_uuid = $this->getUuid();
    $data_url_query = array_filter([
      'current_uri' => $this->getCurrentUri(),
      'map_id' => $chart_id,
    ], fn ($value) => $value !== NULL && $value !== '');
    $map_tabs = NULL;
    $attachments = [
      'library' => ['ghi_blocks/map.gl.plan_composite'],
      'drupalSettings' => [
        'plan_composite_map' => [
          $chart_id => [
            'id' => $chart_id,
            'data_url' => $block_uuid ? Url::fromRoute('ghi_blocks.map_data', [
              'plugin_id' => $this->getPluginId(),
              'block_uuid' => $block_uuid,
            ], [
              'query' => $data_url_query,
            ])->toString() : NULL,
          ],
        ],
      ],
    ];

    if ($this->isConfigurationPreview()) {
      // Preview blocks are rebuilt from unsaved in-memory configuration, so the
      // map payload must be built from this block instance instead of the saved
      // layout block loaded by the lazy map endpoint.
      $payload = $this->buildLazyMapPayload($chart_id);
      if (!$payload->isEmpty()) {
        $map_tabs = $payload->getHtml()['.pane-' . $chart_id . ' .map-tabs--inner'] ?? NULL;
        $attachments = BubbleableMetadata::mergeAttachments($attachments, $payload->getAttachments());
        $attachments['drupalSettings']['plan_composite_map'][$chart_id] = $this->getConfigurationPreviewMap($payload->getMap());
      }
    }

    $build = [
      '#full_width' => FALSE,
    ];
    $build[] = [
      '#theme' => 'plan_attachment_map',
      '#chart_id' => $chart_id,
      '#map_tabs' => $map_tabs,
      '#map_type' => 'composite',
      '#legend' => TRUE,
      '#attached' => $attachments,
    ];

    $comment = $this->buildBlockCommentRenderArray($this->getBlockComment());
    if ($comment) {
      $comment['#attributes']['class'][] = 'content-width';
      $build['comment'] = $comment;
    }

    CacheableMetadata::createFromObject($this->getCurrentBaseObject())
      ->addCacheTags($this->getMapConfigCacheTags())
      ->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildLazyMapPayload(string $map_id): MapPayload {
    $map = $this->buildMap();
    if (empty($map['data'])) {
      return MapPayload::forEmptyMap(
        [
          'id' => $map_id,
          'settings_key' => 'plan_composite_map',
        ],
        MapPayload::cacheabilityFromTags(Cache::mergeTags($this->getCurrentBaseObject()->getCacheTags(), $this->getMapConfigCacheTags())),
      );
    }

    $conf = $this->getBlockConfig();
    $outline_country = NULL;
    $focus_country = $this->getCurrentPlanObject()?->getFocusCountry();
    if ($focus_country instanceof GeoJsonLocationInterface) {
      $outline_country = $focus_country->getGeoJsonLocationData();
      // Keep the existing map client compatible while Fabric locations settle.
      $outline_country['location_id'] = $outline_country['id'];
      $outline_country['location_name'] = $outline_country['name'];
    }

    $map_settings = [
      'json' => $map['data'],
      'id' => $map_id,
      'settings_key' => 'plan_composite_map',
      'disclaimer' => $conf['common']['disclaimer'] ?: $this->getDefaultMapDisclaimer($this->getCurrentPlanObject()->getPlanLanguage()),
      'pcodes_enabled' => $conf['common']['pcodes_enabled'] ?? TRUE,
      'label_min_zoom' => (int) ($conf['common']['label_min_zoom'] ?? 6),
      'style' => 'composite',
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
   */
  private function attachmentCanBeMapped(AttachmentInterface $attachment): bool {
    if (!$attachment instanceof Attachment) {
      return FALSE;
    }
    if (!$attachment->hasDisaggregatedData()) {
      return FALSE;
    }
    return $attachment->canBeMapped('latest');
  }

  /**
   * Build the composite map data.
   */
  private function buildMap(): array {
    $conf = $this->getBlockConfig();
    $maps = $this->getConfiguredItems($conf['maps']['maps'] ?? NULL) ?? [];
    $build = [
      'data' => [],
      'tabs' => [
        '#theme' => 'item_list',
        '#items' => [],
        '#gin_lb_theme_suggestions' => FALSE,
      ],
      'settings' => [],
    ];

    if (empty($maps)) {
      return $build;
    }

    $context = $this->getBlockContext();
    foreach ($maps as $map) {
      /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\CompositeMap $item_type */
      $item_type = $this->getItemTypePluginForColumn($map, $context);
      $map_data = $item_type->buildMapData();
      if (empty($map_data)) {
        continue;
      }
      $build['data'][$item_type->getId()] = $map_data;
      foreach ($item_type->getAttachments() as $attachment) {
        CacheableMetadata::createFromObject($attachment)->applyTo($build);
      }
    }

    if (empty($build['data'])) {
      return $build;
    }

    $this->calculateGroupedSizes($build['data']);
    foreach ($build['data'] as $key => $item) {
      $build['tabs']['#items'][] = [
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
    return $build;
  }

  /**
   * Calculate grouped marker sizes across all map tabs.
   */
  private function calculateGroupedSizes(array &$data): void {
    $ranges = ['min' => 0, 'max' => 0];
    foreach ($data as $tab_data) {
      $metric_type = $tab_data['full_pie']['metric_type'] ?? NULL;
      $attachment_id = $tab_data['full_pie']['attachment']['id'] ?? NULL;
      if (!$metric_type || !$attachment_id) {
        continue;
      }
      $tab_min = array_reduce($tab_data['locations'], function ($carry, $item) use ($metric_type, $attachment_id) {
        $value = $item['metrics'][$attachment_id][$metric_type] ?? 0;
        $value = is_numeric($value) ? $value : 0;
        return $carry > $value ? $value : $carry;
      }, 0);
      $tab_max = array_reduce($tab_data['locations'], function ($carry, $item) use ($metric_type, $attachment_id) {
        $value = $item['metrics'][$attachment_id][$metric_type] ?? 0;
        $value = is_numeric($value) ? $value : 0;
        return $carry < $value ? $value : $carry;
      }, 0);

      $ranges['min'] = min($ranges['min'], $tab_min);
      $ranges['max'] = max($ranges['max'], $tab_max);
    }

    foreach ($data as &$item) {
      $metric_type = $item['full_pie']['metric_type'] ?? NULL;
      $attachment_id = $item['full_pie']['attachment']['id'] ?? NULL;
      if (!$metric_type || !$attachment_id) {
        continue;
      }
      foreach ($item['locations'] as &$location) {
        $max = $ranges['max'];
        $value = $location['metrics'][$attachment_id][$metric_type] ?? 0;
        $relative_size = ($max > 0 ? 10 / $max * $value : 1) * 4;
        $location['radius_factor'] = $relative_size > 1 ? $relative_size : 1;
      }
    }
  }

  /**
   * {@inheritdoc}
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
      'maps' => [
        'maps' => [],
      ],
      'common' => [
        'disclaimer' => NULL,
        'pcodes_enabled' => FALSE,
        'label_min_zoom' => 6,
        'comment' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    if (empty($this->getConfiguredAttachmentIds())) {
      return $subform_key == 'attachments';
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    if (empty($this->getConfiguredAttachmentIds())) {
      return 'attachments';
    }
    return 'maps';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'common';
  }

  /**
   * Form callback for selecting the source attachments.
   */
  public function attachmentsForm(array $form, FormStateInterface $form_state) {
    $form['entity_attachments'] = [
      '#type' => 'entity_attachment_select',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'entity_attachments'),
      '#element_context' => $this->getBlockContext(),
      '#attachment_options' => [
        'attachment_prototypes' => TRUE,
      ],
      '#next_step' => 'maps',
      '#container_wrapper' => $this->getContainerWrapper(),
      '#disagg_warning' => TRUE,
    ];
    return $form;
  }

  /**
   * Form callback for configuring composite map datasets.
   */
  public function mapsForm(array $form, FormStateInterface $form_state) {
    $form['maps'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured datasets'),
      '#title_display' => 'invisible',
      '#edit_label' => $this->t('Edit'),
      '#item_type_label' => $this->t('Map'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'maps'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          'attachment_summary' => $this->t('Attachment(s)'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * Form callback for common map settings.
   */
  public function commonForm(array $form, FormStateInterface $form_state) {
    $form['disclaimer'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Map disclaimer'),
      '#description' => $this->t('You can override the default map disclaimer for this widget.'),
      '#rows' => 4,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'disclaimer',
      ]) ?? '',
    ];

    $form['pcodes_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable pcodes'),
      '#description' => $this->t('If checked, the map will list pcodes alongside location names and enable pcodes for the location filtering.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'pcodes_enabled',
      ]) ?? FALSE,
    ];

    $form['label_min_zoom'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum zoom for labels'),
      '#description' => $this->t('Specifiy at which zoom level the admin area labels become visible. Setting this to <em>0</em> will show them at any zoom level. Default is <em>6</em>.'),
      '#min' => 0,
      '#max' => 9,
      '#step' => 'any',
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, [
        'label_min_zoom',
      ]) ?? 0,
    ];

    $form['comment'] = $this->buildBlockCommentFormElement($this->getDefaultFormValueFromFormState($form_state, [
      'comment',
    ]));

    return $form;
  }

  /**
   * Get the attachment prototype to use for the current block instance.
   */
  private function getAttachmentPrototype() {
    $prototypes = $this->getUniquePrototypes();
    return reset($prototypes);
  }

  /**
   * Get unique prototype options for the selected attachments.
   */
  private function getUniquePrototypes(): array {
    $attachments = $this->getConfiguredAttachments() ?? [];
    $prototype_options = [];
    foreach ($attachments as $attachment) {
      $prototype = $attachment->getPrototype();
      if (!$prototype || array_key_exists($prototype->id(), $prototype_options)) {
        continue;
      }
      $prototype_options[$prototype->id()] = $prototype;
    }
    return $prototype_options;
  }

  /**
   * Get all mappable attachment objects configured for the block.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects keyed by attachment id.
   */
  private function getSelectedAttachments(): array {
    if (empty($this->getConfiguredEntities())) {
      return [];
    }
    $attachments = $this->getConfiguredAttachments();
    return array_filter($attachments, function (AttachmentInterface $attachment) {
      return $this->attachmentCanBeMapped($attachment);
    });
  }

  /**
   * Get the custom context for this block.
   */
  public function getBlockContext(): array {
    $page_node = $this->getPageNode();
    return [
      'page_node' => $page_node,
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $page_node,
      'attachment_prototype' => $this->getAttachmentPrototype(),
      'attachment_ids' => $this->getConfiguredAttachmentIds(),
    ];
  }

  /**
   * Get the configured entity ids.
   */
  private function getConfiguredEntities(): array {
    $conf = $this->getBlockConfig();
    return array_filter($conf['attachments']['entity_attachments']['entities']['entity_ids'] ?? []);
  }

  /**
   * Get the configured attachment ids.
   *
   * @return int[]
   *   An array of configured attachment ids.
   */
  private function getConfiguredAttachmentIds(): array {
    $conf = $this->getBlockConfig();
    $attachment_ids = array_filter($conf['attachments']['entity_attachments']['attachments']['attachment_id'] ?? []);
    return array_values(array_map('intval', $attachment_ids));
  }

  /**
   * Get the available entities.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array of plan entity objects.
   */
  private function getAvailableEntities(): array {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    $plan_id = $plan_object->getSourceId();

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery $plan_query */
    $plan_query = $this->fabricQueryManager->createInstance('plan');
    $plan_entities = [
      $plan_id => $plan_query->getPlan($plan_id),
    ];

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->getQueryHandler('entities');
    $plan_entities += $query->getEntitiesForPlan($plan_id, $this->getCurrentBaseObject()) ?? [];
    return $plan_entities;
  }

  /**
   * Get configured attachment objects.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects.
   */
  private function getConfiguredAttachments(): array {
    $attachment_ids = $this->getConfiguredAttachmentIds();
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    return !empty($attachment_ids) ? $query->getAttachmentsById($attachment_ids) : [];
  }

  /**
   * Get available attachments for the current plan context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[]
   *   An array of attachment objects.
   */
  private function getAvailableAttachments(): array {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    return $query->getAttachmentsForPlan($plan_object->getSourceId(), $this->getCurrentBaseObject(), [
      'Caseload',
      'Indicator',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes(): array {
    return [
      'composite_map' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors(): array {
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
      if ($available_entities && count($configured_entities) != count(array_intersect_key($configured_entities, $available_entities))) {
        $errors[] = $this->t('Some configured entities are not available');
      }
    }

    $configured_attachments = $this->getConfiguredAttachments();
    if (!empty($configured_attachments)) {
      $available_attachments = $this->getAvailableAttachments();
      if ($available_attachments && count($configured_attachments) != count(array_intersect_key($configured_attachments, $available_attachments))) {
        $errors[] = $this->t('Some configured attachments are not available');
      }
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigErrors(): void {
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
      $valid_attachment = array_intersect_key($configured_attachments, $available_attachments);
      $valid_attachment_ids = !empty($valid_attachment) ? array_keys($valid_attachment) : [];
      $conf['attachments']['entity_attachments']['attachments']['attachment_id'] = [];
      if (!empty($valid_attachment_ids)) {
        $conf['attachments']['entity_attachments']['attachments']['attachment_id'] = array_combine($valid_attachment_ids, $valid_attachment_ids);
      }
      else {
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

    $this->setBlockConfig($conf);
  }

}
