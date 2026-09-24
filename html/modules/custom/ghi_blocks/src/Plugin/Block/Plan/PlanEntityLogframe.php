<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\ghi_blocks\Interfaces\CustomLinkBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Logframe\LogframeDownloadSource;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\Plan as ApiObjectsPlan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Entity\GoverningEntity as GoverningEntityObject;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelMultipleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanEntityLogframe' block.
 */
#[Block(
  id: 'plan_entity_logframe',
  admin_label: new TranslatableMarkup('Entity Logframe'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
    'plan_cluster' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Cluster'), required: FALSE, constraints: ['Bundle' => 'governing_entity']),
  ],
)]
class PlanEntityLogframe extends GHIBlockBase implements MultiStepFormBlockInterface, ConfigurableTableBlockInterface, OverrideDefaultTitleBlockInterface, CustomLinkBlockInterface, TrustedCallbackInterface, ConfigValidationInterface, ConfigurationUpdateInterface, HPCDownloadExcelMultipleInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;
  use CustomLinkTrait;
  use ConfigValidationTrait;

  /**
   * Logframe item state for unresolved ajax-loaded rows.
   */
  private const LOGFRAME_ITEM_STATE_PENDING = 'pending';

  /**
   * Logframe item state for rows with expandable content.
   */
  private const LOGFRAME_ITEM_STATE_CONTENT = 'content';

  /**
   * Logframe item state for rows confirmed to have no expandable content.
   */
  private const LOGFRAME_ITEM_STATE_EMPTY = 'empty';

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_subpages\LogframeManager
   */
  public $logframeManager;

  /**
   * The standard logframe table configuration builder.
   *
   * @var \Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder
   */
  protected $tableConfigBuilder;

  /**
   * The block download dialog builder.
   *
   * @var \Drupal\hpc_downloads\DownloadDialog\DownloadDialogPlugin
   */
  protected $downloadDialog;

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      dataSources: [
        'entities' => 'fabric_query:entity',
        'plan' => 'fabric_query:plan',
        'attachment' => 'fabric_query:attachment',
        'attachment_prototype' => 'fabric_query:attachment_prototype',
        'entity_prototype' => 'fabric_query:entity_prototype',
      ],
      configForms: [
        'entities' => [
          'title' => 'Entities',
          'callback' => 'entitiesForm',
        ],
        'tables' => [
          'title' => 'Tables',
          'callback' => 'tablesForm',
          'base_form' => TRUE,
        ],
        'display' => [
          'title' => 'Display',
          'callback' => 'displayForm',
        ],
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->logframeManager = $container->get('ghi_subpages.logframe_manager');
    $instance->tableConfigBuilder = $container->get('ghi_subpages.logframe_table_config_builder');
    $instance->downloadDialog = $container->get('hpc_downloads.download_dialog_plugin');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    $first_entity = !empty($entities) ? reset($entities) : NULL;
    if ($first_entity instanceof ApiObjectsPlan) {
      return $this->t('Response plan', [], ['langcode' => $this->getCurrentPlanObject()?->getPlanLanguage()]);
    }
    if (!$first_entity instanceof EntityObjectInterface) {
      return NULL;
    }
    return count($entities) > 1 ? $first_entity->getPluralName() : $first_entity->getSingularName();
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (empty($conf['entities']['entity_ids'])) {
      return 'entities';
    }
    return 'tables';
  }

  /**
   * Retrieve the renderable entities for this instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[]
   *   An array of preprocessed HPC entities.
   */
  private function getRenderableEntities() {
    $conf = $this->getBlockConfig();
    if (empty($conf['entities']['entity_ref_code'])) {
      return [];
    }

    $matching_entities = $this->getPlanEntities($conf['entities']['entity_ref_code']);
    if (empty($matching_entities)) {
      // Nothing to render.
      return [];
    }
    $valid_entities = $this->getValidPlanEntities($matching_entities, $conf);
    if (empty($valid_entities)) {
      // Nothing to render.
      return [];
    }
    return $valid_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->getRenderableEntities());
  }

  /**
   * {@inheritdoc}
   */
  protected function hasReliableIsEmpty(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = parent::build();
    if (empty($build)) {
      return $build;
    }

    // Add the links here already so that the custum link appears alongside
    // the download link added in GHIBlockBase.
    $build['links'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['link-wrapper'],
      ],
    ];

    $display_conf = $this->getBlockConfig()['display'];
    $link = $this->getLinkFromConfiguration($display_conf['link'] ?? [], [
      'section_node' => $this->getCurrentSectionNode(),
      'page_node' => $this->getPageNode(),
    ]);
    if (!empty($display_conf['full_logframe_download'])) {
      $build['links']['full_logframe'] = $this->buildFullLogframeDownloadLinks();
    }
    if ($link) {
      $build['links'][] = $link->toRenderable();
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    if (empty($entities)) {
      return;
    }

    // Get the config.
    $conf = $this->getBlockConfig();

    // Sort the entities.
    $this->sortPlanEntities($entities, $conf['entities']);

    // See if we should use lazy loading for the tables.
    $lazy_load = $this->config('ghi_blocks.logframe_settings')->get('lazy_load');

    $inline_tables = $this->isConfigurationPreview() || !$lazy_load;
    if ($inline_tables) {
      // Preload the attachments to reduce the number of queries.
      $this->getAttachmentsForEntities($entities);
    }

    // Assemble the list.
    $rendered_items = [];
    foreach ($entities as $entity) {
      if ($inline_tables) {
        $fragment = $this->buildLogframeItemFragment($entity);
      }
      elseif (!$fragment = $this->getCachedLogframeItemFragment($entity)) {
        $fragment = [
          'state' => self::LOGFRAME_ITEM_STATE_PENDING,
        ];
      }
      $rendered_items[] = $this->buildLogframeItemRenderArray($entity, $fragment);
    }
    $count = count($rendered_items);

    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    $first_entity = reset($entities);
    $build = [];
    $build['content'] = [
      '#theme' => 'plan_entity_logframe',
      '#items' => $rendered_items,
      '#tooltip_show_data' => $this->t('Show data', [], ['langcode' => $langcode]),
      '#tooltip_hide_data' => $this->t('Hide data', [], ['langcode' => $langcode]),
      '#tooltip_no_data' => $this->t('No data', [], ['langcode' => $langcode]),
      '#wrapper_attributes' => [
        'class' => [
          'plan-entity-logframe',
          $count >= 5 ? 'up-5' : 'up-' . $count,
          Html::getClass('entity-type--' . $first_entity->getEntityType()),
        ],
      ],
      '#gin_lb_theme_suggestions' => FALSE,
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->createLogframeDownloadSource()->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadSource() {
    $request = $this->requestStack->getCurrentRequest();
    $download_routes = ['hpc_downloads.download_dialog', 'hpc_downloads.initiate'];
    $scope = in_array($request?->attributes->get('_route'), $download_routes, TRUE) ? $request->query->get('logframe_scope') : NULL;
    return $scope !== NULL ? $this->createLogframeDownloadSource($scope) : parent::getDownloadSource();
  }

  /**
   * Supplies context and services to the workbook builder.
   *
   * @param string|null $scope
   *   Plan or cluster, or NULL for the configured selection.
   *
   * @return \Drupal\ghi_blocks\Logframe\LogframeDownloadSource
   *   The download source.
   */
  protected function createLogframeDownloadSource(?string $scope = NULL): LogframeDownloadSource {
    $configuration = [];
    if ($scope === NULL) {
      $configuration = $this->getBlockConfig();
      $context = $this->getBlockContext();
      // Table configuration uses its own default ordering; downloads must
      // preserve source order unless the editor explicitly configured sorting.
      $context['entities'] = $this->getRenderableEntities();
      $this->sortPlanEntities($context['entities'], $configuration['entities']);
    }
    else {
      // Full exports deliberately ignore the displayed entity/table selection.
      $plan = $this->getCurrentPlanObject();
      $context = [
        'plan_object' => $plan,
        'base_object' => $this->getCurrentBaseObject(),
        'section_node' => $this->getCurrentSectionNode(),
        'page_node' => $this->getPageNode(),
        'entity_types' => $plan ? $this->logframeManager->getEntityTypesFromPlanObject($plan) : [],
      ];
    }
    $source = new LogframeDownloadSource($this, $context, $configuration, $this->fabricQueryManager, $this->configurationContainerItemManager, $this->tableConfigBuilder, $scope);
    $source->setStringTranslation($this->getStringTranslation());
    return $source;
  }

  /**
   * Gets the full-logframe scopes available in this block's context.
   *
   * @return array
   *   Download labels keyed by scope.
   */
  public function getFullLogframeDownloadOptions(): array {
    $base_object = $this->getCurrentBaseObject();
    return LogframeDownloadSource::getScopeOptions($this->getCurrentPlanObject(), $base_object instanceof GoverningEntityObject ? $base_object : NULL);
  }

  /**
   * Builds a single plan action or a dropdown of plan and cluster actions.
   *
   * @return array
   *   The download actions.
   */
  private function buildFullLogframeDownloadLinks(): array {
    $links = [];
    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    $scopes = $this->getFullLogframeDownloadOptions();
    foreach ($scopes as $scope => $label) {
      $link = $this->downloadDialog->buildDialogLink($this, $label, $label, $langcode)['#link'];
      $url = $link['#url'];
      $query = $url->getOption('query');
      $query['logframe_scope'] = $scope;
      $url->setOption('query', $query);
      if (count($scopes) == 1) {
        $attributes = $url->getOption('attributes');
        $attributes['class'][] = 'cd-button';
        $url->setOption('attributes', $attributes);
      }
      $links[$scope] = $link;
    }
    if (count($links) < 2) {
      return $links;
    }
    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['logframe-download-options']],
      '#attached' => ['library' => ['common_design/cd-dropdown']],
      'options' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['cd-dropdown'],
          'data-cd-component' => 'ghi-download',
          'data-cd-icon' => 'arrow-down',
          'data-cd-toggable' => $this->t('Download logframe', [], ['langcode' => $langcode]),
        ],
        'links' => ['#type' => 'container'] + $links,
      ],
    ];
  }

  /**
   * Check if there is download data for this element.
   *
   * @return bool
   *   TRUE if there is something to download, FALSE otherwise.
   */
  private function hasDownloadData(): bool {
    return $this->createLogframeDownloadSource()->hasConfiguredData();
  }

  /**
   * {@inheritdoc}
   */
  public function preprocess(&$variables) {
    parent::preprocess($variables);
    if (empty($variables['download_links'])) {
      return;
    }
    $t_args = [
      'langcode' => $this->getCurrentPlanObject()->getPlanLanguage() ?? 'en',
    ];

    if ($this->hasDownloadData()) {
      // Move the download link into a different position.
      $variables['content']['links'] = $variables['content']['links'] ?? [];
      $download_links = array_map(function ($link) use ($t_args) {
        /** @var \Drupal\Core\Url $url */
        $url = $link['#link']['#url'];
        $attributes = $url->getOption('attributes');
        $attributes['class'][] = 'cd-button';
        $url->setOption('attributes', $attributes);
        $link['#link']['#title'] = $this->t('Download', [], $t_args);
        return $link['#link'];
      }, $variables['download_links']);
      $variables['content']['links'] = array_merge($download_links, $variables['content']['links']);
    }

    unset($variables['download_links']);
  }

  /**
   * Get the formatted plan entity description according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param bool $truncate_description
   *   Whether to truncate the description or not.
   *
   * @return string|null
   *   The formatted plan entity description.
   */
  private function getPlanEntityDescription(PlanEntityInterface $entity, $truncate_description = FALSE): ?string {
    $description = $entity->getDescription();
    return ($truncate_description && $description !== NULL) ? Unicode::truncate($description, 120, TRUE, TRUE) : $description;
  }

  /**
   * Get the entity attachment tables according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $conf
   *   The table configuration.
   *
   * @return array
   *   An array of entity attachment tables.
   */
  public function buildTables(PlanEntityInterface $entity, array $conf) {
    return empty($conf['attachment_tables']) ? [] : $this->buildAttachmentTables($entity, $conf, $this->getBlockContext());
  }

  /**
   * Get the entity attachment tables in a container.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   *
   * @return array
   *   A render array with the entity attachment tables.
   */
  public function buildTablesContainer(PlanEntityInterface $entity, array $conf) {
    $tables = $this->buildTables($entity, $conf);
    if (empty($tables)) {
      return $tables;
    }
    return [
      '#type' => 'container',
      'tables' => array_values($tables),
    ];
  }

  /**
   * Build the fragment for one resolved logframe item.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   *
   * @return array
   *   The resolved logframe item fragment.
   */
  private function buildLogframeItemFragment(PlanEntityInterface $entity): array {
    $tables = $this->buildTablesContainer($entity, $this->getBlockConfig()['tables']);
    $contributes_heading = $entity instanceof PlanEntity ? $this->buildContributesToHeading($entity) : NULL;
    $has_content = !empty($tables) || !empty($contributes_heading);

    return [
      'state' => $has_content ? self::LOGFRAME_ITEM_STATE_CONTENT : self::LOGFRAME_ITEM_STATE_EMPTY,
      'contributes_heading' => $contributes_heading,
      'attachment_tables' => $tables ?: NULL,
      'attachment_wrapper_attributes' => $has_content ? $this->buildTablesWrapperAttributes($entity) : [],
    ];
  }

  /**
   * Build a render array for one logframe item.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $fragment
   *   The logframe item fragment.
   *
   * @return array
   *   The logframe item render array.
   */
  private function buildLogframeItemRenderArray(PlanEntityInterface $entity, array $fragment): array {
    $conf = $this->getBlockConfig();
    $langcode = $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en';
    $state = $fragment['state'] ?? self::LOGFRAME_ITEM_STATE_PENDING;

    return [
      '#theme' => 'plan_entity_logframe_item',
      '#label' => $this->getPlanEntityId($entity, $conf['entities']),
      '#description' => $this->getPlanEntityDescription($entity),
      '#state' => $state,
      '#contributes_heading' => $fragment['contributes_heading'] ?? NULL,
      '#attachment_tables' => $fragment['attachment_tables'] ?? NULL,
      '#wrapper_attributes' => $this->buildItemWrapperAttributes($entity, $state),
      '#attachment_wrapper_attributes' => $fragment['attachment_wrapper_attributes'] ?? [],
      '#tooltip_show_data' => $this->t('Show data', [], ['langcode' => $langcode]),
      '#tooltip_hide_data' => $this->t('Hide data', [], ['langcode' => $langcode]),
      '#tooltip_no_data' => $this->t('No data', [], ['langcode' => $langcode]),
    ];
  }

  /**
   * Build attributes for a logframe item wrapper.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param string $state
   *   The logframe item state.
   *
   * @return array
   *   Wrapper attributes.
   */
  private function buildItemWrapperAttributes(PlanEntityInterface $entity, string $state): array {
    $attributes = [
      'data-logframe-block' => $this->getUuid(),
      'data-logframe-entity' => $entity->id(),
      'data-logframe-state' => $state,
    ];
    if ($state == self::LOGFRAME_ITEM_STATE_PENDING) {
      $attributes['data-logframe-item-url'] = Url::fromRoute('ghi_blocks.load_logframe_item', [
        'plugin_id' => $this->getPluginId(),
        'block_uuid' => $this->getUuid(),
        'entity_id' => $entity->id(),
      ], [
        'query' => [
          'current_uri' => $this->getPageNode()?->toUrl()?->toString() ?? $this->getCurrentUri(),
        ],
      ])->toString();
    }
    return $attributes;
  }

  /**
   * Build attributes for an attachment tables wrapper.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   *
   * @return array
   *   Wrapper attributes.
   */
  private function buildTablesWrapperAttributes(PlanEntityInterface $entity): array {
    return [
      'data-logframe-block' => $this->getUuid(),
      'data-logframe-entity' => $entity->id(),
      'data-logframe-loaded' => 'true',
    ];
  }

  /**
   * Get a cached resolved item fragment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   *
   * @return array|null
   *   The cached fragment or NULL.
   */
  private function getCachedLogframeItemFragment(PlanEntityInterface $entity): ?array {
    $fragment = $this->cache($this->getLogframeItemFragmentCacheKey($entity));
    return is_array($fragment) && !empty($fragment['state']) ? $fragment : NULL;
  }

  /**
   * Store a resolved item fragment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $fragment
   *   The resolved fragment.
   */
  private function setCachedLogframeItemFragment(PlanEntityInterface $entity, array $fragment): void {
    $this->cache($this->getLogframeItemFragmentCacheKey($entity), $fragment, FALSE, NULL, $this->getLogframeItemFragmentCacheTags());
  }

  /**
   * Build a cache key for one resolved item fragment.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   *
   * @return string
   *   The cache key.
   */
  private function getLogframeItemFragmentCacheKey(PlanEntityInterface $entity): string {
    $page_node = $this->getPageNode();
    $parts = [
      'plugin_id' => $this->getPluginId(),
      'block_uuid' => $this->getUuid(),
      'block_config' => md5(json_encode($this->getBlockConfig())),
      'current_user_roles' => json_encode($this->currentUser->getRoles()),
      'current_uri' => $this->getCurrentUri(),
      'page_node' => $page_node?->id(),
      'plan' => $this->getCurrentPlanId(),
      'entity' => $entity->id(),
      'langcode' => $this->getCurrentPlanObject()?->getPlanLanguage() ?? 'en',
    ] + $this->getPageArguments();
    ksort($parts);
    return 'ghi_blocks:plan_entity_logframe:item:' . hash('sha256', serialize($parts));
  }

  /**
   * Get cache tags for resolved item fragments.
   *
   * @return string[]
   *   The cache tags.
   */
  private function getLogframeItemFragmentCacheTags(): array {
    return array_values(array_diff($this->getCacheTags(), [
      $this->getLogframeBlockCacheTag(),
    ]));
  }

  /**
   * Get the block tag used by the full block-content cache.
   *
   * @return string
   *   The block cache tag.
   */
  private function getLogframeBlockCacheTag(): string {
    return $this->getPluginId() . ':' . $this->getUuid();
  }

  /**
   * Build the ajax replacement item for one entity.
   *
   * @param int $entity_id
   *   The logframe entity id.
   *
   * @return array
   *   A logframe item render array.
   */
  public function buildAjaxLogframeItem(int $entity_id): array {
    $entities = $this->getRenderableEntities();
    if (!array_key_exists($entity_id, $entities)) {
      return [];
    }

    $entity = $entities[$entity_id];
    $fragment = $this->getCachedLogframeItemFragment($entity);
    if (!$fragment) {
      $fragment = $this->buildLogframeItemFragment($entity);
      $this->setCachedLogframeItemFragment($entity, $fragment);
      Cache::invalidateTags([$this->getLogframeBlockCacheTag()]);
    }
    return $this->buildLogframeItemRenderArray($entity, $fragment);
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'entities' => [
        'entity_ids' => NULL,
        'entity_ref_code' => NULL,
        'id_type' => NULL,
        'sort' => FALSE,
        'sort_column' => NULL,
      ],
      'tables' => [
        'attachment_tables' => [],
      ],
      'display' => [
        'title' => NULL,
        'link' => NULL,
        'full_logframe_download' => FALSE,
      ],
    ];
  }

  /**
   * Form builder for the entities form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function entitiesForm(array $form, FormStateInterface $form_state) {
    $entity_ref_code_options = $this->getEntityRefCodeOptions();
    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');
    $ajax_parents = array_merge($form['#array_parents'] ?? []);
    array_pop($ajax_parents);

    $page_node = $this->getPageNode();

    // Get the defaults for easier access.
    $defaults = [
      'entity_ref_code' => $this->getDefaultFormValueFromFormState($form_state, 'entity_ref_code') ?: array_key_first($entity_ref_code_options),
      'id_type' => $this->getDefaultFormValueFromFormState($form_state, 'id_type') ?: NULL,
      'sort' => $this->getDefaultFormValueFromFormState($form_state, 'sort') ?: NULL,
      'sort_column' => $this->getDefaultFormValueFromFormState($form_state, 'sort_column') ?: NULL,
      'entity_ids' => $this->getDefaultFormValueFromFormState($form_state, 'entity_ids') ?: NULL,
    ];
    $form['type_container'] = [
      '#type' => 'container',
      '#parents' => ['type_container'],
      '#attributes' => [
        'class' => ['group-container'],
      ],
    ];
    $form['entity_ref_code'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#description' => $this->t('The entity type, e.g. <em>Cluster Objective</em> or <em>Strategic Objective</em>'),
      '#options' => $entity_ref_code_options,
      '#default_value' => $defaults['entity_ref_code'],
      '#required' => count($entity_ref_code_options),
      '#disabled' => empty($entity_ref_code_options),
      '#group' => 'type_container',
    ];
    // The ID type selector is only visible on non-logframe pages and if the
    // selected entity type is not a the plan.
    $ref_code_selector = FormElementHelper::getStateSelector($form, ['entity_ref_code']);
    $form['id_type'] = [
      '#type' => 'select',
      '#title' => $this->t('ID type'),
      '#description' => $this->t('Define how to show the ID. See the table below for a preview.'),
      '#options' => AttachmentHelper::idTypes(),
      '#default_value' => $defaults['id_type'],
      '#disabled' => empty($entity_ref_code_options),
      '#states' => [
        'visible' => [
          'select[name="' . $ref_code_selector . '"]' => ['!value' => ApiObjectsPlan::ENTITY_REF_CODE],
        ],
      ],
      '#group' => 'type_container',
    ];
    if ($page_node instanceof LogframeSubpage) {
      $form['id_type'] = [
        '#value' => 'custom_id_prefixed_refcode',
        '#type' => 'hidden',
      ];
    }

    // If we have a plan context, add checkboxes to select individual entities.
    if ($this->getCurrentPlanId() && count($entity_ref_code_options)) {
      // Bind ajax callback for auto-update of available entities when the type
      // is changed.
      $form['entity_ref_code']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => $ajax_parents,
      ];

      $form['id_type']['#ajax'] = [
        'event' => 'change',
        'callback' => [$this, 'updateAjax'],
        'wrapper' => $wrapper_id,
        'array_parents' => $ajax_parents,
      ];

      $matching_entities = [];
      $entity_options = [];

      $matching_entities = $this->getPlanEntities($defaults['entity_ref_code']);
      if (count($matching_entities)) {
        $this->sortPlanEntities($matching_entities, $defaults);
        // Assemble the list.
        $entity_options = [];
        foreach ($matching_entities as $entity) {
          $entity_options[$entity->id()] = [
            'id' => $this->getPlanEntityId($entity, $defaults),
            'description' => $this->getPlanEntityDescription($entity, TRUE),
          ];
        }
      }

      $form['sort_container'] = [
        '#type' => 'container',
        '#parents' => ['sort_container'],
        '#attributes' => [
          'class' => ['group-container'],
        ],
      ];

      $form['sort'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Sort the data'),
        '#default_value' => $defaults['sort'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => $ajax_parents,
        ],
        '#group' => 'sort_container',
      ];
      $form['sort_column'] = [
        '#type' => 'select',
        '#title' => $this->t('Sort column'),
        '#title_display' => 'invisible',
        '#options' => [
          'id_' . SORT_ASC => $this->t('ID (asc)'),
          'id_' . SORT_DESC => $this->t('ID (desc)'),
          'description_' . SORT_ASC => $this->t('Description (asc)'),
          'description_' . SORT_DESC => $this->t('Description (desc)'),
        ],
        '#default_value' => $defaults['sort_column'],
        '#states' => [
          'visible' => [
            ':input[name="entities[sort]"]' => ['checked' => TRUE],
          ],
        ],
        '#ajax' => [
          'event' => 'change',
          'callback' => [$this, 'updateAjax'],
          'wrapper' => $wrapper_id,
          'array_parents' => $ajax_parents,
        ],
        '#group' => 'sort_container',
      ];

      $form['entity_ids_header'] = [
        '#type' => 'markup',
        '#markup' => $this->t('If you do not want to show all entities of this type, select the ones that should be visible below. If no entity is selected, all entities will be shown. Please note that some rows might not be available for selection because of incomplete data sets. These will also be hidden from public display.'),
        '#prefix' => '<div>',
        '#suffix' => '</div><br />',
      ];

      $form['entity_ids'] = [
        '#type' => 'tableselect',
        '#header' => [
          'id' => $this->t('ID'),
          'description' => $this->t('Description'),
        ],
        '#options' => $entity_options,
        '#default_value' => !empty($defaults['entity_ids']) ? array_combine($defaults['entity_ids'], $defaults['entity_ids']) : [],
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#empty' => $this->t('No suitable entities found. If you save this form like this, the block will not be displayed.'),
      ];

      if (count($matching_entities)) {
        $validation_options = $defaults;
        $validation_options['entity_ids'] = NULL;
        foreach ($matching_entities as $entity) {
          if ($this->validatePlanEntity($entity, $validation_options)) {
            continue;
          }
          $form['entity_ids'][$entity->id()]['#disabled'] = TRUE;
        }
      }
    }

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
    ];

    $form['actions']['select_entities'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use selected entities'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'tables',
    ];
    return $form;
  }

  /**
   * Form builder for the tables form.
   *
   * @param array $form
   *   An associative array containing the initial structure of the subform.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The full form array for this subform.
   */
  public function tablesForm(array $form, FormStateInterface $form_state) {
    $form['attachment_tables'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured attachment tables'),
      '#title_display' => 'invisible',
      '#edit_label' => $this->t('Type'),
      '#item_type_label' => $this->t('Attachment table'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'attachment_tables'),
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Table'),
          'prototype' => $this->t('Type'),
          'columns_summary' => $this->t('Columns'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['full_logframe_download'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show full logframe download'),
      '#description' => $this->t('Offer the complete plan logframe using the standard metrics. On cluster pages, also offer the current cluster logframe.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'full_logframe_download'),
    ];
    $form['link'] = [
      '#type' => 'custom_link',
      '#title' => $this->t('Add a link to this element'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'link'),
      '#element_context' => $this->getBlockContext(),
    ];
    return $form;
  }

  /**
   * Get options for the entity type dropdown.
   *
   * @return array
   *   An array with valid options for the current context.
   */
  private function getEntityRefCodeOptions() {
    if (!$this->getCurrentPlanObject()) {
      return [];
    }
    return $this->logframeManager?->getEntityTypesFromPlanObject($this->getCurrentPlanObject()) ?: [];
  }

  /**
   * Get available plan entities for the current context.
   *
   * @param string $entity_ref_code
   *   The entity type to restrict the context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[]
   *   An array of plan entity objects for the current context.
   */
  private function getPlanEntities($entity_ref_code = NULL) {
    $context_object = $this->getCurrentBaseObject();

    if ($entity_ref_code == ApiObjectsPlan::ENTITY_REF_CODE && $context_object instanceof Plan) {
      /** @var \Drupal\ghi_plans\Plugin\FabricQuery\PlanQuery $query */
      $query = $this->getQueryHandler('plan');
      $plan = $query?->getPlan($context_object->getSourceId()) ?? NULL;
      return $plan ? [
        $plan->id() => $plan,
      ] : [];
    }

    $filter = NULL;
    if ($entity_ref_code) {
      $filter = ['ref_code' => $entity_ref_code];
    }

    $plan_id = $this->getCurrentPlanId();

    // Preload the entity prototypes.
    $entity_prototype_query = $this->getQueryHandler('entity_prototype');
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery $entity_prototype_query */
    $entity_prototype_query?->getPlanPrototype($plan_id);

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->getQueryHandler('entities');
    $entities = $query?->getEntitiesForPlan($plan_id, $context_object, NULL, $filter) ?? [];
    // This should give us plan and governing entity objects only, but let's
    // make sure.
    $entities = is_array($entities) ? array_filter($entities, function ($entity) {
      return $entity instanceof EntityObjectInterface;
    }) : [];
    return $entities;
  }

  /**
   * Get entities that are valid for display.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The entity objects to check.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]
   *   An array with the entity objects that passed validation.
   */
  private function getValidPlanEntities(array $entities, array $conf) {
    $valid_entities = [];
    if (empty($entities)) {
      return $valid_entities;
    }
    foreach ($entities as $entity) {
      if (!$this->validatePlanEntity($entity, $conf)) {
        continue;
      }
      $valid_entities[$entity->id()] = $entity;
    }
    return $valid_entities;
  }

  /**
   * Sort entities according to the given configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The entity objects to sort.
   * @param array $conf
   *   The current element configuration used to apply validation.
   */
  private function sortPlanEntities(array &$entities, $conf) {
    if (!empty($conf['sort'])) {
      [$key, $sort] = explode('_', $conf['sort_column']);
      // BC code to handle outdated configuration where the sort direction is
      // still given as a string.
      $sort = match ($sort) {
        'ASC' => SORT_ASC,
        'DESC' => SORT_DESC,
        default => $sort,
      };
      // Properties are protected and can't be accessed directly, so we need to
      // use a getter if it exists.
      $callback = 'get' . ucfirst(strtolower($key));
      uasort($entities, function ($a, $b) use ($key, $callback, $sort, $conf) {
        $a_value = $key == 'id' ? $this->getPlanEntityId($a, $conf) : (method_exists($a, $callback) ? ($a->$callback() ?? 0) : 0);
        $b_value = $key == 'id' ? $this->getPlanEntityId($b, $conf) : (method_exists($b, $callback) ? ($b->$callback() ?? 0) : 0);
        return $sort == SORT_DESC ? strnatcmp($b_value, $a_value) : strnatcmp($a_value, $b_value);
      });
    }
  }

  /**
   * Validate that the given entity is valid for display.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   An entity object.
   * @param array $conf
   *   The current element configuration used to apply validation.
   *
   * @return object
   *   True if the entity passed validation, False otherwhise.
   */
  private function validatePlanEntity(PlanEntityInterface $entity, array $conf) {
    $entity_ids = !empty($conf['entities']['entity_ids']) ? array_filter($conf['entities']['entity_ids']) : [];
    if (!empty($entity_ids) && !in_array($entity->id(), $entity_ids)) {
      return FALSE;
    }
    if (empty($entity->getDescription())) {
      return FALSE;
    }
    if (empty($this->getPlanEntityId($entity, $conf))) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    $plan_object = $this->getCurrentPlanObject();
    $plan_entities = $this->getRenderableEntities();
    $this->sortPlanEntities($plan_entities, [
      'sort' => TRUE,
      'sort_column' => 'id_ASC',
    ]);
    // This can be any parent node, e.g. SectionNode or PlanClusterNode.
    $base_entity = $this->getCurrentBaseEntity();
    $section_node = $this->sectionManager->getCurrentSection($base_entity);
    $page_node = $this->getPageNode();
    return [
      'section_node' => $section_node,
      'page_node' => $page_node,
      'plan_object' => $plan_object,
      'base_object' => $this->getCurrentBaseObject(),
      'context_node' => $page_node,
      'entities' => $plan_entities,
      'entity_types' => $plan_object ? $this->logframeManager->getEntityTypesFromPlanObject($plan_object) : [],
      'attachment_prototypes' => $this->getAttachmentPrototypes(),
      'used_attachment_prototypes' => $this->getUsedAttachmentPrototypeIds(),
    ];
  }

  /**
   * Get the ids of already used attachment prototypes.
   *
   * @return int[]
   *   An array of attachment prototype ids.
   */
  private function getUsedAttachmentPrototypeIds($conf = NULL) {
    $conf = $conf !== NULL ? ($conf['tables'] ?? []) : $this->getBlockConfig()['tables'] ?? [];
    $attachment_prototype_ids = [];
    foreach ($conf['attachment_tables'] as $table) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($table, []);
      $attachment_prototype_ids[] = $item_type->get('attachment_prototype');
    }
    return $attachment_prototype_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'attachment_table' => [],
    ];
    return $item_types;
  }

  /**
   * Get the available attachment prototypes for the current plan context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype[]
   *   An array of attachment prototypes.
   */
  private function getAttachmentPrototypes() {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery $query */
    $query = $this->getQueryHandler('attachment_prototype');
    if (!$query) {
      return [];
    }
    $attachment_prototypes = $query->getDataPrototypesForPlan($plan_object->getSourceId());

    $entity_ref_code = $this->getBlockConfig()['entities']['entity_ref_code'] ?? NULL;
    $entity_ref_codes = array_filter([$entity_ref_code]);
    if (empty($entity_ref_code)) {
      $entity_ref_codes = array_keys($this->logframeManager->getEntityTypesFromPlanObject($plan_object));
    }
    return $this->filterAttachmentPrototypesByEntityRefCodes($attachment_prototypes, $entity_ref_codes);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors() {
    $conf = $this->getBlockConfig();
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

    $configured_entities = array_filter($conf['entities']['entity_ids'] ?? []);
    $available_entities = $this->getPlanEntities($conf['entities']['entity_ref_code']);

    if (!empty($configured_entities) && $available_entities && count($configured_entities) != count(array_intersect_key($configured_entities, $available_entities))) {
      $errors[] = $this->t('Some configured entities are not available');
    }

    $items = $this->getConfiguredItemPlugins($conf['tables']['attachment_tables'] ?? [], $this->getBlockContext());
    if (empty($items)) {
      $errors[] = $this->t('No configured tables');
    }
    else {
      foreach ($items as $item) {
        if (!$item->isValid()) {
          $errors[] = $this->t('@item_type: @errors', [
            '@item_type' => $item->getLabel(),
            '@errors' => implode(', ', $item->getConfigurationErrors()),
          ]);
        }
      }
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigErrors() {
    $conf = $this->getBlockConfig();

    $entities = $this->getRenderableEntities();
    $configured_entities = array_filter($conf['entities']['entity_ids'] ?? []);
    if (!empty($configured_entities)) {
      $available_entities = $this->getPlanEntities($conf['entities']['entity_ref_code']);
      $valid_entity_ids = array_intersect_key($configured_entities, $available_entities);
      $conf['entities']['entity_ids'] = array_combine($valid_entity_ids, $valid_entity_ids);
    }
    else {
      $conf['entities']['entity_ids'] = array_fill_keys(array_keys($entities), 0);
    }

    $entity_ref_code = $conf['entities']['entity_ref_code'] ?? NULL;
    $context = $this->getBlockContext();
    if (!empty($entity_ref_code)) {
      $context['entity_types'] = array_intersect_key($context['entity_types'], [$entity_ref_code => TRUE]);
    }
    $items = $this->getConfiguredItemPlugins($conf['tables']['attachment_tables'] ?? [], $context);
    if (empty($items)) {
      return;
    }
    foreach ($items as $key => $item) {
      $item->setContextValue('used_attachment_prototypes', $this->getUsedAttachmentPrototypeIds($conf));
      if ($item->isValid()) {
        continue;
      }
      $item->fixConfigurationErrors();
      if ($item->isValid()) {
        $conf['tables']['attachment_tables'][$key]['config'] = $item->getConfig();
      }
      else {
        unset($conf['tables']['attachment_tables'][$key]);
      }
    }
    $this->setBlockConfig($conf);
  }

  /**
   * {@inheritdoc}
   */
  public function updateConfiguration() {
    $configuration = &$this->configuration;
    if (empty($configuration['hpc']) || empty($configuration['hpc']['entities']) || empty($configuration['hpc']['entities']['id_type'])) {
      return FALSE;
    }
    if ($configuration['hpc']['entities']['id_type'] == 'custom_id_prefixed_refcode') {
      return FALSE;
    }
    $configuration['hpc']['entities']['id_type'] = 'custom_id_prefixed_refcode';
    return TRUE;
  }

}
