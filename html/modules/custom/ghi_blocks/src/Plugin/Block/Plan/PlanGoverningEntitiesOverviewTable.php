<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\AttachmentContextItemInterface;
use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Plugin\HPCBlockMetadata;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanGoverningEntitiesOverviewTable' block.
 */
#[Block(
  id: 'plan_governing_entities_overview_table',
  admin_label: new TranslatableMarkup('Governing Entities Overview Table'),
  category: new TranslatableMarkup('Plan elements'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
    'plan' => new EntityContextDefinition('entity:base_object', new TranslatableMarkup('Plan'), constraints: ['Bundle' => 'plan']),
  ],
)]
class PlanGoverningEntitiesOverviewTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface, AttachmentTableInterface, ConfigValidationInterface {

  use ConfigurationContainerTrait;
  use ConfigurationItemClusterRestrictTrait;
  use TableSoftLimitTrait;
  use AttachmentTableTrait;
  use ConfigValidationTrait;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

  /**
   * {@inheritdoc}
   */
  public static function metadata(): ?HPCBlockMetadata {
    return new HPCBlockMetadata(
      defaultTitle: 'Cluster overview',
      dataSources: [
        'entities' => 'fabric_query:entity',
        'entity_prototype' => 'fabric_query:entity_prototype',
        'attachment_prototype' => 'fabric_query:attachment_prototype',
        'attachment' => 'fabric_query:attachment',
        'flow_search' => 'hpc_api:flow_search_query',
      ],
      configForms: [
        'data' => [
          'title' => 'Data',
          'callback' => 'dataForm',
        ],
        'table' => [
          'title' => 'Table columns',
          'callback' => 'tableForm',
        ],
        'display' => [
          'title' => 'Display',
          'callback' => 'displayForm',
          'base_form' => TRUE,
        ],
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->subpageManager = $container->get('ghi_subpages.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->buildTableData()['rows']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $table_data = $this->buildTableData();
    $build = empty($table_data['rows']) ? [] : [
      '#theme' => 'table',
      '#header' => $table_data['header'],
      '#rows' => $table_data['rows'],
      '#sortable' => FALSE,
      '#soft_limit' => $table_data['soft_limit'],
      '#progress_groups' => TRUE,
      '#block_id' => $this->getBlockId(),
    ];
    foreach ($this->queryHandlers as $query) {
      $table_data['cacheability']->addCacheTags($query->getCacheTags() ?? []);
    }
    $table_data['cacheability']->applyTo($build);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function getContentCacheKeyParts(): array {
    // GVE links and row inclusion can vary by node grants within one role.
    return parent::getContentCacheKeyParts() + ['account' => $this->currentUser->id()];
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);
    $trigger = $form_state->getTriggeringElement();
    // Editors must be able to switch to the columns step to fix metrics after
    // choosing a different source. Validate the combination on final save.
    if (($trigger['#parents'] ?? NULL) !== ['actions', 'submit']) {
      return;
    }
    foreach ($this->getConfigErrors() as $error) {
      $form_state->setErrorByName($form_state->get('current_subform') ?? 'data', $error);
    }
  }

  /**
   * Build the table data for this element.
   *
   * @return array
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData(): array {
    $conf = $this->getBlockConfig();
    $columns = $this->getConfiguredItems($conf['table']['columns']) ?? [];
    $cacheability = (new CacheableMetadata())->addCacheTags(['node_list', 'base_object_list']);
    $result = ['header' => [], 'rows' => [], 'soft_limit' => 0, 'cacheability' => $cacheability];
    if (!$columns || !$this->getCurrentPlanObject()) {
      return $result;
    }

    $entities = $this->getEntityObjects();
    if (!empty($conf['data']['cluster_restrict'])) {
      $entities = $this->applyClusterRestrictFilterToEntities($entities, $conf['data']['cluster_restrict']);
    }
    $has_attachments = $this->hasAttachmentColumns();
    $has_funding = in_array('funding_data', array_column($columns, 'item_type'), TRUE);
    $attachments = $has_attachments ? ($this->getAttachmentsForEntities($entities) ?? []) : [];
    // Resolve the source against the plan, even if cluster restrictions leave
    // no ordinary rows. Plan-level special funding rows can still be shown.
    $prototype = $has_attachments ? $this->getAttachmentPrototype() : NULL;
    if ($has_attachments && !$prototype) {
      return $result;
    }
    $grouped = [];
    if ($prototype) {
      $attachments = $this->filterAttachmentsByPrototype($attachments, $prototype->id());
      // Keep dependencies even when a filter removes the last matching row.
      foreach ($attachments as $attachment) {
        $cacheability->addCacheTags($attachment->getValueCacheTags());
      }
      $this->prefetchDisaggregatedDataAvailability($attachments, $columns);
      $grouped = $this->groupAttachmentsByEntityId($attachments);
    }
    $objects = $this->loadBaseObjectsForEntities($entities);
    $subpages = $objects ? $this->subpageManager->loadSubpagesForBaseObjects($objects) : [];
    usort($entities, fn ($a, $b) => strnatcasecmp($a->getDisplayName(), $b->getDisplayName()));
    if ($has_funding && $entities) {
      $this->getQueryHandler('attachment')->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY, array_map(fn ($entity) => $entity->id(), $entities), ['cost']);
    }
    $context = $this->getBlockContext();
    $rows = [];
    $group_count = 0;
    $soft_limit = 0;
    foreach ($entities as $entity) {
      $base_object = $objects[$entity->id()] ?? NULL;
      if (!$base_object) {
        continue;
      }
      $cacheability->addCacheableDependency($base_object);
      $subpage = $subpages[$base_object->id()] ?? NULL;
      $access = $subpage?->access('view', NULL, TRUE);
      if ($subpage) {
        $cacheability->addCacheableDependency($subpage)->addCacheableDependency($access);
      }
      if ($subpage && (!$subpage->isPublished() || !$access->isAllowed()) && empty($conf['data']['include_unpublished_clusters'])) {
        continue;
      }
      $context['base_object'] = $base_object;
      $context['context_node'] = $subpage;
      $context['entity'] = $entity;
      $context['attachment'] = NULL;
      $context['raw_data'] = NULL;
      $group = $this->buildEntityRows($columns, $context, $grouped[$entity->id()] ?? [], $cacheability);
      if (!$group) {
        continue;
      }
      $rows = array_merge($rows, $group);
      $group_count++;
      // The existing soft-limit behavior counts physical rows. Translate the
      // configured GVE count at a group boundary so no caseload is cut off.
      if ($group_count <= ($conf['display']['soft_limit'] ?? 0)) {
        $soft_limit = count($rows);
      }
    }
    if ($has_funding) {
      $rows = array_merge($rows, $this->buildSpecialFundingRows($columns, $context, $cacheability));
    }
    foreach ($this->queryHandlers as $query) {
      $cacheability->addCacheTags($query->getCacheTags() ?? []);
    }
    return [
      'header' => $this->buildTableHeader($columns),
      'rows' => $rows,
      'soft_limit' => $soft_limit,
      'cacheability' => $cacheability,
    ];
  }

  /**
   * Get cacheability metadata from a table cell.
   *
   * @param array $cell
   *   The table cell.
   * @param \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type
   *   The item type plugin that built the cell.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   The cacheability metadata for the cell.
   */
  private function getTableCellCacheability(array $cell, ConfigurationContainerItemPluginInterface $item_type): CacheableMetadata {
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags($item_type->getCacheTags());
    if (is_array($cell['data'] ?? NULL)) {
      $cacheability = $cacheability->merge(CacheableMetadata::createFromRenderArray($cell['data']));
    }
    return $cacheability;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'data' => [
        'cluster_restrict' => [],
        'prototype_id' => NULL,
        'include_unpublished_clusters' => FALSE,
      ],
      'table' => [
        'columns' => [],
      ],
      'display' => [
        'soft_limit' => NULL,
        'include_cluster_not_reported' => FALSE,
        'include_shared_funding' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    $conf = $this->getBlockConfig();
    if (!empty($conf['table']) && !empty($conf['table']['columns'])) {
      return 'table';
    }
    return 'data';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * Form callback for the data settings form.
   */
  public function dataForm(array $form, FormStateInterface $form_state) {

    $prototype_options = $this->getUniquePrototypeOptions();
    $form['prototype_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Attachment prototype'),
      '#options' => $prototype_options,
      '#empty_option' => $this->t('- Select -'),
      '#description' => $this->t('Select a caseload source for population columns. Financial columns do not require a caseload source.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'prototype_id'),
    ];
    if (count($prototype_options) == 1) {
      $form['prototype_id']['#access'] = FALSE;
      $form['prototype_id']['#value'] = array_key_first($prototype_options);
    }

    $form['include_unpublished_clusters'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include unpublished clusters'),
      '#description' => $this->t('Include clusters whose subpages are unpublished or unavailable to the visitor. This applies to all columns; links are only shown when the visitor can access the subpage.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'include_unpublished_clusters'),
    ];

    $form['cluster_restrict'] = $this->buildClusterRestrictFormElement($this->getDefaultFormValueFromFormState($form_state, 'cluster_restrict'));
    return $form;
  }

  /**
   * Form callback for the table configuration form.
   */
  public function tableForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'columns');
    if (empty($default_value)) {
      $default_value = [[
        'item_type' => 'entity_name',
        'config' => ['label' => $this->getGenericEntityName()],
      ],
      ];
    }
    $allowed = $this->getAllowedItemTypes();
    $prototype = $this->getAttachmentPrototype();
    $allowed['data_point']['attachment_prototype'] = $prototype;
    $context = $this->getBlockContext();
    $context['attachment_prototype'] = $prototype;
    if (!$prototype) {
      foreach ($allowed as $item_type => $options) {
        if ($this->isAttachmentItemType($options['item_type_base'] ?? $item_type)) {
          unset($allowed[$item_type]);
        }
      }
    }
    $form['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured table columns'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Column'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $allowed,
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
        ],
      ],
      '#element_context' => $context,
      '#row_filter' => TRUE,
    ];
    return $form;
  }

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, 'soft_limit'));
    $form['soft_limit']['#description'] = $this->t('Limits the number of clusters initially shown, not the number of table rows. All caseload subrows of each cluster are shown together. Leave empty to show all clusters.');

    $form['include_cluster_not_reported'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include a line with funding not reported to a cluster'),
      '#description' => $this->t('Check this if you want an additional line added that only shows the plan funding that has not been reported to a specific cluster.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'include_cluster_not_reported'),
    ];

    $form['include_shared_funding'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include a line with funding shared across multiple clusters'),
      '#description' => $this->t('Check this if you want an additional line added that only shows the plan funding that is shared across multiple clusters.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'include_shared_funding'),
    ];

    return $form;
  }

  /**
   * Get all governing entity objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity[]
   *   An array of governing entity objects, aka clusters.
   */
  public function getEntityObjects(): array {
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityQuery $query */
    $query = $this->getQueryHandler('entities');
    $entities = $query?->getEntitiesForPlan($this->getCurrentPlanId(), $this->getPageNode(), 'governing') ?? [];
    $entities = array_filter($entities, fn (EntityObjectInterface $entity): bool => $entity instanceof GoverningEntity);
    return $entities;
  }

  /**
   * Load the nodes associated to the entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[] $entities
   *   The entity objects.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   An array of node objects.
   */
  private function loadBaseObjectsForEntities(array $entities) {
    $entity_ids = array_map(function ($entity) {
      return $entity->id();
    }, $entities);

    return BaseObjectHelper::getBaseObjectsFromOriginalIds($entity_ids, 'governing_entity') ?? [];
  }

  /**
   * Get the first entity node for column configuration.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface|null
   *   The first entity node available.
   */
  private function getFirstEntityObject() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return NULL;
    }
    $entity = reset($entities);
    $entity_nodes = $this->loadBaseObjectsForEntities([$entity]);
    return !empty($entity_nodes) ? reset($entity_nodes) : NULL;
  }

  /**
   * Get a generic name for entities in this element.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup
   *   The generic entity name.
   */
  private function getGenericEntityName() {
    $context = $this->getBlockContext();
    $plan = $context['plan_object'];
    assert($plan instanceof Plan);

    // Prefer the structure label; PlanClusterType can differ from the actual
    // governing entity prototype used for table rows.
    if ($prototype_label = $this->getGenericEntityPrototypeName($plan)) {
      return $prototype_label;
    }

    $t_options = ['langcode' => $plan->getPlanLanguage()];
    $cluster_label_map = [
      Plan::CLUSTER_TYPE_CLUSTER => $this->t('Cluster', [], $t_options),
      Plan::CLUSTER_TYPE_SECTOR => $this->t('Sector', [], $t_options),
    ];
    return $cluster_label_map[$plan->getPlanClusterType()] ?? $cluster_label_map[Plan::CLUSTER_TYPE_CLUSTER];
  }

  /**
   * Get the singular governing entity label from the plan prototype.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   *
   * @return string|null
   *   The prototype label, or NULL if the plan prototype is unavailable.
   */
  private function getGenericEntityPrototypeName(Plan $plan): ?string {
    $plan_id = $plan->getSourceId();
    if (!$plan_id) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\EntityPrototypeQuery|null $prototype_query */
    $prototype_query = $this->getQueryHandler('entity_prototype');
    $plan_prototype = $prototype_query?->getPlanPrototype($plan_id);
    if (!$plan_prototype) {
      return NULL;
    }

    foreach ($plan_prototype->getEntityPrototypes() as $entity_prototype) {
      if ($entity_prototype->isGoverningEntity()) {
        return $entity_prototype->getNameSingular();
      }
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    $entity = $this->getFirstEntityObject();
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $entity,
      'context_node' => NULL,
      'attachment_prototype' => $this->hasAttachmentColumns() ? $this->getAttachmentPrototype() : NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'entity_name' => [],
      'funding_data' => [
        'cluster_restrict' => FALSE,
        'value_preview' => FALSE,
        'fts_link' => TRUE,
      ],
      'data_point' => [
        'attachment_prototype' => $this->hasAttachmentColumns() ? $this->getAttachmentPrototype() : NULL,
        'disaggregation_modal' => TRUE,
        'handle_empty_data' => TRUE,
        'select_monitoring_period' => TRUE,
      ],
      'spark_line_chart' => [],
      'monitoring_period' => [],
      'project_counter' => [
        'access' => [
          'plan_costing' => [0, 1, 3],
        ],
        'value_preview' => FALSE,
        'options' => [
          'link' => TRUE,
          'include_popup' => TRUE,
        ],
      ],
    ];
    return $item_types;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    $table_data = $this->buildTableData();
    return $table_data ? [
      'header' => $table_data['header'],
      'rows' => $table_data['rows'],
    ] : $table_data;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
    if (empty($entities)) {
      return NULL;
    }
    $entity_ids = array_map(function ($entity) {
      return $entity->id();
    }, $entities);

    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query */
    $query = $this->getQueryHandler('attachment');
    return $query->getAttachmentsByObject(PlanEntityInterface::ENTITY_TYPE_GOVERNING_ENTITY, $entity_ids, 'caseload');
  }

  /**
   * {@inheritdoc}
   */
  public function getUniquePrototypes(?array $attachments = NULL) {
    $plan_id = $this->getCurrentPlanId();
    if (!$plan_id) {
      return [];
    }
    // Read definitions independently of attachments so editors can configure
    // population columns before the first caseload has been reported.
    /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery|null $query */
    $query = $this->getQueryHandler('attachment_prototype');
    $prototypes = $query?->getDataPrototypesForPlan($plan_id) ?? [];
    $prototypes = array_filter($prototypes, fn ($prototype) => $prototype->getType() === 'caseload');
    $ref_codes = array_filter(array_map(fn ($entity) => $entity->getEntityTypeRefCode(), $this->getEntityObjects()));
    return $this->filterAttachmentPrototypesByEntityRefCodes($prototypes, $ref_codes);
  }

  /**
   * Get caseload prototype options for the plan's governing entities.
   *
   * @return array
   *   An array of prototype names, keyed by the prototype id.
   */
  private function getUniquePrototypeOptions() {
    $prototypes = $this->getUniquePrototypes();
    return array_map(function ($prototype) {
      return $prototype->getName();
    }, $prototypes);
  }

  /**
   * Group the given attachments by the governing entity id.
   *
   * @param array $attachments
   *   An array of attachment objects as returned by
   *   AttachmentQuery::getAttachmentsByObject().
   *
   * @return array
   *   An array of arrays of attachment objects, keyed by the entity id.
   */
  private function groupAttachmentsByEntityId(array $attachments) {
    $grouped_attachements = [];
    foreach ($attachments as $attachment) {
      $entity_id = $attachment->getSourceEntityId();
      if (!array_key_exists($entity_id, $grouped_attachements)) {
        $grouped_attachements[$entity_id] = [];
      }
      $grouped_attachements[$entity_id][] = $attachment;
    }

    return $grouped_attachements;
  }

  /**
   * Filter the given set of attachments by the given prototype id.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Attachments\Attachment[] $attachments
   *   The attachments to filter.
   * @param int $prototype_id
   *   The prototype id to filter for.
   *
   * @return array
   *   An array of attachment objects that passed the filter.
   */
  private function filterAttachmentsByPrototype(array $attachments, $prototype_id) {
    return array_filter($attachments, function ($attachment) use ($prototype_id) {
      return $attachment->getPrototype()?->id() == $prototype_id;
    });
  }

  /**
   * Build optional plan-level funding rows using the financial table semantics.
   *
   * @param array $columns
   *   Configured columns.
   * @param array $context
   *   The block context.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Accumulated cell cacheability.
   *
   * @return array
   *   Special funding rows.
   */
  private function buildSpecialFundingRows(array $columns, array $context, CacheableMetadata &$cacheability): array {
    $conf = $this->getBlockConfig();
    $rows = [];
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery $flow_search_query */
    $flow_search_query = $this->getQueryHandler('flow_search');

    // If configured accordingly, add a "Cluster not specified row".
    if (!empty($conf['display']['include_cluster_not_reported']) && $conf['display']['include_cluster_not_reported']) {

      $not_specified_entity = $flow_search_query->getNotSpecifiedCluster();

      if ($not_specified_entity && !empty($not_specified_entity->total_funding)) {
        $context['attachment'] = NULL;
        $context['base_object'] = NULL;
        $context['context_node'] = NULL;
        $context['entity'] = NULL;
        $context['raw_data'] = $not_specified_entity;

        $row = [];
        foreach ($columns as $column) {
          /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
          $item_type = clone $this->getItemTypePluginForColumn($column, $context);

          if ($item_type->getPluginId() == 'entity_name') {
            $not_reported_label = Markup::create('<i>' . $this->t('Funding to @title not reported', [
              '@title' => strtolower($this->getGenericEntityName()),
            ]) . '</i>');
            $row[] = [
              'data' => $not_reported_label,
              'class' => array_merge($item_type->getClasses(), [
                'not-reported',
              ]),
              'data-raw-value' => $not_reported_label,
              'data-sort-type' => $item_type::SORT_TYPE,
              'data-column-type' => $item_type->getColumnType(),
            ];
          }
          elseif ($item_type->getPluginId() == 'funding_data' && $item_type->get('data_type') == 'funding_totals') {
            // Add the funding.
            $cell = $item_type->getTableCell();
            $cacheability = $cacheability->merge($this->getTableCellCacheability($cell, $item_type));
            $row[] = $cell;
          }
          else {
            $row[] = [
              'data' => $this->t('N/A'),
              'data-value' => NULL,
              'export_value' => NULL,
              'excel_format' => 'string',
              'data-raw-value' => NULL,
              'data-sort-type' => $item_type::SORT_TYPE,
              'data-column-type' => $item_type->getColumnType(),
              'class' => array_merge($item_type->getClasses(), [
                'not-reported',
                'empty',
              ]),
            ];
          }
        }
        $rows[] = $row;
      }
    }

    if (!empty($conf['display']['include_shared_funding']) && $conf['display']['include_shared_funding'] && $flow_search_query->hasSharedClusterFunding()) {
      $context['attachment'] = NULL;
      $context['base_object'] = NULL;
      $context['context_node'] = NULL;
      $context['entity'] = NULL;
      $context['raw_data'] = (object) [
        'total_funding' => $flow_search_query->getSharedClusterFunding(),
      ];

      $row = [];
      foreach ($columns as $column) {
        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = clone $this->getItemTypePluginForColumn($column, $context);

        if ($item_type->getPluginId() == 'entity_name') {
          $not_reported_label = Markup::create('<i>' . $this->t('Funding to multiple @title (shared)', [
            '@title' => strtolower($this->getGenericEntityName()),
          ]) . '</i>');
          $row[] = [
            'data' => $not_reported_label,
            'class' => array_merge($item_type->getClasses(), [
              'shared-funding',
            ]),
            'data-raw-value' => $not_reported_label,
            'data-sort-type' => $item_type::SORT_TYPE,
            'data-column-type' => $item_type->getColumnType(),
          ];
        }
        elseif ($item_type->getPluginId() == 'funding_data' && $item_type->get('data_type') == 'funding_totals') {
          /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\FundingData $item_type */
          $item_type->disableFtsLink();
          // Add the funding.
          $cell = $item_type->getTableCell();
          $cacheability = $cacheability->merge($this->getTableCellCacheability($cell, $item_type));
          $row[] = $cell;
        }
        else {
          $row[] = [
            'data' => $this->t('N/A'),
            'data-value' => NULL,
            'export_value' => NULL,
            'excel_format' => 'string',
            'data-raw-value' => NULL,
            'data-sort-type' => $item_type::SORT_TYPE,
            'data-column-type' => $item_type->getColumnType(),
            'class' => array_merge($item_type->getClasses(), [
              'shared-funding',
              'empty',
            ]),
          ];
        }
      }
      $rows[] = $row;
    }
    return $rows;
  }

  /**
   * Check whether the configured columns need a caseload source.
   *
   * @return bool
   *   TRUE when attachment-based columns are configured.
   */
  private function hasAttachmentColumns(): bool {
    $columns = $this->getBlockConfig()['table']['columns'] ?? [];
    // During AJAX rebuilds, columns also contains item-editor input without
    // a plugin ID. Only complete IDs can be checked against plugin definitions.
    foreach (array_column($columns, 'item_type') as $item_type) {
      if (is_string($item_type) && $item_type !== '' && $this->isAttachmentItemType($item_type)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Check a plugin's attachment requirement before row context is available.
   *
   * @param string $item_type
   *   The configuration container item plugin ID.
   *
   * @return bool
   *   Whether values belong to the attachment supplied in the row context.
   */
  private function isAttachmentItemType(string $item_type): bool {
    // Instantiating through getItemTypePluginForColumn() would build allowed
    // items and context, which themselves depend on hasAttachmentColumns().
    $definition = $this->getConfigurationContainerItemManager()->getDefinition($item_type, FALSE);
    return $definition && is_a($definition['class'], AttachmentContextItemInterface::class, TRUE);
  }

  /**
   * Build complete rows for one governing entity.
   *
   * @param array $columns
   *   Configured columns.
   * @param array $context
   *   Governing entity context.
   * @param array $attachments
   *   Matching caseloads after prototype filtering.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Accumulated cacheability, including filtered cells.
   *
   * @return array
   *   Complete GVE group, or an empty array when excluded.
   */
  private function buildEntityRows(array $columns, array $context, array $attachments, CacheableMetadata &$cacheability): array {
    // Evaluate GVE-level columns once. They filter the entire group, whereas
    // attachment filters only apply to their own caseload row.
    $entity_cells = $this->buildCells($columns, $context, FALSE, $cacheability);
    if ($entity_cells === NULL) {
      return [];
    }
    $attachment_rows = [];
    foreach ($attachments as $attachment) {
      $context['attachment'] = $attachment;
      $cells = $this->buildCells($columns, $context, TRUE, $cacheability);
      if ($cells !== NULL) {
        $attachment_rows[] = ['attachment' => $attachment, 'cells' => $cells];
      }
    }
    // Missing caseloads retain the cluster, but explicit population filters
    // that reject every existing caseload must still exclude it.
    if ($attachments && !$attachment_rows) {
      return [];
    }
    $row_attributes = [
      'data-entity-id' => $context['entity']->id(),
      'data-entity-type' => 'governing-entity',
    ];
    // Preserve the source grouping even if filters leave only one child.
    if (count($attachments) > 1 && $attachment_rows) {
      $rows = [['data' => $entity_cells] + $row_attributes];
      foreach ($attachment_rows as $attachment_row) {
        $cells = $attachment_row['cells'];
        foreach (array_values($columns) as $index => $column) {
          if ($column['item_type'] == 'entity_name') {
            $description = $attachment_row['attachment']->getDescription();
            if ($description === NULL || trim($description) === '') {
              $description = (string) $this->t('Caseload @id', ['@id' => $attachment_row['attachment']->id()]);
            }
            $cells[$index] = [
              'data' => ['#markup' => '<span class="name">' . Html::escape($description) . '</span>'],
              'data-value' => $description,
              'data-raw-value' => $description,
              'export_value' => $description,
              'data-column-type' => 'name',
              'class' => array_merge($entity_cells[$index]['class'] ?? [], ['subrow']),
            ];
          }
        }
        $rows[] = ['data' => $cells, 'data-attachment-id' => $attachment_row['attachment']->id()] + $row_attributes;
      }
      return $rows;
    }
    if ($attachment_rows) {
      foreach (array_values($columns) as $index => $column) {
        if ($this->getItemTypePluginForColumn($column, $context) instanceof AttachmentContextItemInterface) {
          $entity_cells[$index] = $attachment_rows[0]['cells'][$index];
        }
      }
    }
    return [['data' => $entity_cells] + $row_attributes];
  }

  /**
   * Build applicable cells, leaving other dimensions explicitly empty.
   *
   * @param array $columns
   *   Configured columns.
   * @param array $context
   *   Context for the current row.
   * @param bool $attachment_row
   *   Whether to evaluate attachment columns instead of GVE columns.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Accumulated cell cacheability.
   *
   * @return array|null
   *   Cells, or NULL if a filter excludes this row.
   */
  private function buildCells(array $columns, array $context, bool $attachment_row, CacheableMetadata &$cacheability): ?array {
    $cells = [];
    $passes = TRUE;
    foreach ($columns as $key => $column) {
      $item = $this->getItemTypePluginForColumn($column, $context);
      if (($item instanceof AttachmentContextItemInterface) !== $attachment_row) {
        // Text format keeps the Excel exporter from converting missing
        // percentage values into numeric zeroes.
        $cells[] = [
          'data' => NULL,
          'export_value' => NULL,
          'excel_format' => 'string',
          'data-raw-value' => NULL,
          'data-column-type' => $item->getColumnType(),
          'class' => array_merge($item->getClasses(), ['empty']),
        ];
        continue;
      }
      $cell = $item->getTableCell();
      $cacheability = $cacheability->merge($this->getTableCellCacheability($cell, $item));
      if ($item->getColumnType() == 'amount') {
        $cell['data-progress-group'] = 'amount-' . $key;
      }
      $cells[] = $cell;
      $passes = ($item->checkFilter() !== FALSE) && $passes;
    }
    return $passes ? $cells : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAttachmentPrototype($attachments = NULL) {
    $prototype_id = $this->getBlockConfig()['data']['prototype_id'] ?? NULL;
    $prototypes = $this->getUniquePrototypes($attachments);
    if ($prototype_id) {
      return $prototypes[$prototype_id] ?? NULL;
    }
    // Selecting an arbitrary first source can silently change the meaning of
    // configured population columns when a plan offers multiple prototypes.
    return count($prototypes) == 1 ? reset($prototypes) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigErrors() {
    if (!$this->getCurrentPlanObject()) {
      return [$this->t('No plan object available on the target page.')];
    }
    if (!$this->hasAttachmentColumns()) {
      return [];
    }
    $prototype = $this->getAttachmentPrototype();
    if (!$prototype) {
      return [$this->t('Select a valid caseload source before configuring population columns.')];
    }
    foreach ($this->getBlockConfig()['table']['columns'] ?? [] as $column) {
      $metrics = [];
      if ($column['item_type'] == 'data_point') {
        $config = $column['config']['data_point'];
        $metrics[] = $config['data_points'][0]['metric_type'] ?? NULL;
        if (($config['processing'] ?? 'single') == 'calculated') {
          $metrics[] = $config['data_points'][1]['metric_type'] ?? NULL;
        }
      }
      elseif ($column['item_type'] == 'spark_line_chart') {
        $metrics[] = $column['config']['data_point'] ?? NULL;
        if (!empty($column['config']['show_baseline'])) {
          $metrics[] = $column['config']['baseline'] ?? NULL;
        }
      }
      foreach ($metrics as $metric) {
        if (!in_array($metric, $prototype->getFieldTypes(), TRUE)) {
          return [$this->t('A configured population metric is unavailable in the selected caseload source. Review the table columns.')];
        }
      }
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function fixConfigErrors() {
    // New instances are configured explicitly; changing context must not
    // silently select a different source or remap an editor's columns.
  }

}
