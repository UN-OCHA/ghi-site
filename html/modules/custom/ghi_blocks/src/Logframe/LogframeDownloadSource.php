<?php

namespace Drupal\ghi_blocks\Logframe;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\Attachment;
use Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\Plan as ApiObjectsPlan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\GoverningEntity as GoverningEntityObject;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder;
use Drupal\hpc_api\Query\FabricQueryManager;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_downloads\DownloadMethods\Excel;
use Drupal\hpc_downloads\DownloadSource\BlockSource;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Builds configured, plan and cluster logframe workbooks.
 *
 * Workbook generation uses explicit context and injected services. The plugin
 * is only used by the existing download dialog, metadata and filename adapter.
 */
class LogframeDownloadSource extends BlockSource {

  use AttachmentTableTrait;
  use ConfigurationContainerTrait;

  /**
   * Query plugins shared by the worksheets in this download.
   *
   * @var \Drupal\hpc_api\Query\FabricQueryBase[]
   */
  private array $queries = [];

  /**
   * Constructs a logframe download source.
   *
   * @param \Drupal\hpc_downloads\Interfaces\HPCDownloadPluginInterface $plugin
   *   The plugin used for download dialog integration and metadata.
   * @param array $context
   *   Table context including plan_object and optional base_object. Configured
   *   downloads also supply sorted entities and filtered attachment_prototypes.
   * @param array $configuration
   *   The entity and table settings for a configured download.
   * @param \Drupal\hpc_api\Query\FabricQueryManager $queryManager
   *   The Fabric query manager.
   * @param \Drupal\ghi_form_elements\ConfigurationContainerItemManager $itemManager
   *   The attachment table plugin manager.
   * @param \Drupal\ghi_subpages\Logframe\LogframeTableConfigBuilder $tableConfigBuilder
   *   The standard metric configuration builder.
   * @param string|null $scope
   *   Plan or cluster, or NULL for the configured selection.
   */
  public function __construct(HPCDownloadPluginInterface $plugin, protected array $context, protected array $configuration, protected FabricQueryManager $queryManager, protected ConfigurationContainerItemManager $itemManager, protected LogframeTableConfigBuilder $tableConfigBuilder, protected ?string $scope = NULL) {
    parent::__construct($plugin);
    if ($scope !== NULL && !array_key_exists($scope, $this->getAvailableScopes())) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDialogOptions() {
    return parent::getDialogOptions() + ($this->scope !== NULL ? ['logframe_scope' => $this->scope] : []);
  }

  /**
   * {@inheritdoc}
   */
  public function getData() {
    return $this->scope === NULL ? $this->buildConfiguredData() : $this->buildFullData();
  }

  /**
   * {@inheritdoc}
   */
  public function getMetaData() {
    $data = parent::getMetaData();
    if ($this->scope !== NULL) {
      $object = $this->scope == 'plan' ? $this->context['plan_object'] : $this->context['base_object'];
      $data[1][1] = $object->label();
      $data[] = [
        (string) $this->t('Download scope'),
        (string) $this->getAvailableScopes()[$this->scope],
      ];
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getDownloadFileName($type) {
    // Keep simultaneous block, plan and cluster exports in separate files.
    return parent::getDownloadFileName($type) . ($this->scope !== NULL ? '_' . $this->scope . '_logframe' : '');
  }

  /**
   * Gets the full-logframe scopes available for a plan and optional cluster.
   *
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan
   *   The plan to download.
   * @param \Drupal\ghi_plans\Entity\GoverningEntity|null $cluster
   *   The optional cluster context.
   *
   * @return array
   *   Download labels keyed by scope.
   */
  public static function getScopeOptions(?Plan $plan, ?GoverningEntityObject $cluster = NULL): array {
    if (!$plan) {
      return [];
    }
    $options = ['langcode' => $plan->getPlanLanguage()];
    $scopes = ['plan' => new TranslatableMarkup('Download full logframe', [], $options)];
    if ($cluster !== NULL) {
      $scopes['cluster'] = $plan->getPlanClusterType() == Plan::CLUSTER_TYPE_SECTOR ?
        new TranslatableMarkup('Download sector logframe', [], $options) :
        new TranslatableMarkup('Download cluster logframe', [], $options);
    }
    return $scopes;
  }

  /**
   * Gets the scopes supported by the supplied plan and cluster context.
   *
   * @return array
   *   Download labels keyed by scope.
   */
  private function getAvailableScopes(): array {
    $base_object = $this->context['base_object'] ?? NULL;
    return self::getScopeOptions($this->context['plan_object'] ?? NULL, $base_object instanceof GoverningEntityObject ? $base_object : NULL);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    return ['attachment_table' => []];
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return $this->context;
  }

  /**
   * {@inheritdoc}
   */
  protected function getConfigurationContainerItemManager() {
    return $this->itemManager;
  }

  /**
   * Gets a request-local identity for the table plugin cache.
   *
   * @return string
   *   A cache namespace unique to this download source.
   */
  public function getUuid(): string {
    return spl_object_hash($this);
  }

  /**
   * Gets a query plugin without depending on block data-source configuration.
   *
   * @param string $name
   *   The query name.
   *
   * @return \Drupal\hpc_api\Query\FabricQueryBase
   *   The query plugin.
   */
  protected function getQueryHandler(string $name) {
    $plugin_id = $name == 'entities' ? 'entity' : $name;
    return $this->queries[$name] ??= $this->queryManager->createInstance($plugin_id);
  }

  /**
   * Checks whether the configured selection has download data.
   *
   * @return bool
   *   TRUE if there is something to download, FALSE otherwise.
   */
  public function hasConfiguredData(): bool {
    $conf = $this->configuration;
    if (empty($conf['entities']['entity_ref_code'])) {
      return FALSE;
    }

    $entities = $this->context['entities'] ?? [];
    if (empty($entities)) {
      return FALSE;
    }

    if ($this->buildLogicalFrameworkExcelsheet($entities)) {
      return TRUE;
    }

    // Preload the attachments to reduce the number of queries.
    $this->getAttachmentsForEntities($entities);

    foreach ($entities as $entity) {
      $tables = $this->buildAttachmentTables($entity, $conf['tables'], $this->context);
      foreach ($tables as $table) {
        if (!empty($table['#rows'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Builds the workbook from the selected entities and configured tables.
   *
   * @return array|null
   *   Worksheet data, or NULL when no entities are selected.
   */
  private function buildConfiguredData() {
    $data = [];

    // Get the entities to render.
    $entities = $this->context['entities'] ?? [];
    if (empty($entities)) {
      return;
    }

    // Get the config.
    $conf = $this->configuration;

    // Prepare the sheet for the logical framework.
    $logframe_sheet_label = (string) $this->t('Logical framework');
    $data[$logframe_sheet_label] = [];
    $logical_framework = [
      'header' => [],
      'rows' => [],
    ];

    $plan = $this->context['plan_object'];
    $t_options = ['langcode' => $plan->getPlanLanguage()];
    $cluster_label_map = [
      Plan::CLUSTER_TYPE_CLUSTER => $this->t('Cluster', [], $t_options),
      Plan::CLUSTER_TYPE_SECTOR => $this->t('Sector', [], $t_options),
    ];
    $cluster_args = [
      '@cluster_label' => $cluster_label_map[$plan->getPlanClusterType()],
    ];

    // Collect the entity parents once, the cluster associations, both for the
    // alignments in general and also on an per-entity/per-parent basis.
    $entity_cluster_alignments = [];
    foreach ($entities as $entity) {
      $entity_cluster_alignments[$entity->id()] = $entity instanceof PlanEntity ? $entity->getParentGoverningEntity(TRUE) : NULL;
    }

    // Build the header for the logframe sheet.
    if ($logical_framework = $this->buildLogicalFrameworkExcelsheet($entities, $cluster_args, $t_options)) {
      $data[$logframe_sheet_label] = $logical_framework;
    }
    else {
      unset($data[$logframe_sheet_label]);
    }

    // Collect the table names for deduplication.
    $table_names = [];
    $attachment_prototypes = $this->context['attachment_prototypes'];

    // Build the actual data tables if applicable, one for each configured
    // table.
    foreach ($entities as $entity) {
      $tables = $this->buildAttachmentTables($entity, $conf['tables'], $this->context);
      foreach ($tables as $key => $table) {
        /** @var \Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype $prototype */
        $prototype = $attachment_prototypes[$table['#prototype_id'] ?? NULL] ?? NULL;
        if (!$prototype) {
          continue;
        }
        $table_names[$prototype->id()] = $table['#download_label'];

        if (!array_key_exists($key, $data)) {
          // Add additional table columns at the beginning of each table.
          $additional_header = [
            (string) $this->t('@ref_code description', [
              '@ref_code' => $conf['entities']['entity_ref_code'],
            ], $t_options),
          ];
          if (!empty($entity_cluster_alignments[$entity->id()])) {
            $additional_header[] = (string) $this->t('@cluster_label name', $cluster_args, $t_options);
          }
          // Get the type name either from the prototype or from the first data
          // column of type "name".
          $name_columns = array_filter($table['#header'], function ($cell) {
            return $cell['data-column-type'] == 'name';
          });
          $entity_type_name = $name_columns[0]['data'] ?? $prototype->getName();
          $additional_header[] = trim((string) $this->t('@entity_type_name customRef', [
            '@entity_type_name' => $entity_type_name,
          ], $t_options));

          $data[$key] = [
            'header' => array_merge($additional_header, $table['#header']),
            'rows' => [],
          ];
        }
        $entity_rows = array_map(function ($row) use ($entity, $entity_cluster_alignments) {
          $additional_columns = [
            $entity->getDescription(),
          ];
          if (!empty($entity_cluster_alignments[$entity->id()])) {
            $additional_columns[] = $entity_cluster_alignments[$entity->id()]->getDisplayName();
          }

          $additional_columns[] = $row['data-attachment-custom-id'];
          $row['data'] = array_merge($additional_columns, $row['data'] ?? $row);

          return $row;
        }, $table['#rows']);
        $data[$key]['rows'] = array_merge($data[$key]['rows'], $entity_rows);

        if ($prototype?->isIndicator()) {
          // Indicators should include a column with the calculation method.
          $this->addCalculationMethodColumnToExcelData($data[$key], $t_options);
        }
      }
    }

    // Deduplicate the table names just in case.
    $table_names = ArrayHelper::deduplicateStrings($table_names);
    // And replace the data keys.
    foreach ($table_names as $prototype_id => $table_name) {
      $data[$table_name] = $data[$prototype_id];
      unset($data[$prototype_id]);
    }

    foreach (array_keys($data) as $key) {
      if (in_array($key, [$logframe_sheet_label])) {
        continue;
      }
      $this->processSparklineChartInExcelData($data[$key], $t_options);
    }
    return $data;
  }

  /**
   * Builds a complete workbook independently of the displayed block selection.
   *
   * @return array
   *   The logical framework and one data worksheet per attachment prototype.
   */
  private function buildFullData(): array {
    $scope = $this->scope;
    $plan = $this->context['plan_object'];
    $plan_id = $plan->getSourceId();
    $t_options = ['langcode' => $plan->getPlanLanguage()];
    $this->getQueryHandler('entity_prototype')->getPlanPrototype($plan_id);
    $query = $this->getQueryHandler('entities');
    // IDs belong to separate source namespaces and must not overwrite each
    // other when governing and plan entities share the same numeric ID.
    $entities = array_merge(array_values($query->getEntitiesForPlan($plan_id, NULL, 'governing')), array_values($query->getEntitiesForPlan($plan_id, NULL, 'plan')));
    $excluded_ref_codes = ['CQ', 'HC'];
    $entities = array_filter($entities, fn ($entity) => $entity instanceof PlanEntityInterface && !in_array($entity->getEntityTypeRefCode(), $excluded_ref_codes));
    $api_plan = $this->getQueryHandler('plan')->getPlan($plan_id);
    if ($scope == 'cluster') {
      $entities = $this->filterFullLogframeEntitiesForCluster($entities, $this->context['base_object']->getSourceId());
    }
    elseif ($api_plan) {
      array_unshift($entities, $api_plan);
    }

    $prototypes = $this->getQueryHandler('attachment_prototype')->getDataPrototypesForPlan($plan_id);
    $context = [
      'plan_object' => $plan,
      'base_object' => $scope == 'cluster' ? $this->context['base_object'] : $plan,
      'section_node' => $this->context['section_node'] ?? NULL,
      'page_node' => $this->context['page_node'] ?? NULL,
      'context_node' => $this->context['page_node'] ?? NULL,
      'entities' => $entities,
      'entity_types' => $this->context['entity_types'] ?? [],
      'attachment_prototypes' => $prototypes,
      'used_attachment_prototypes' => [],
    ];
    // Prime the object store in bulk before building the per-entity tables.
    $this->getAttachmentsForEntities($entities);
    $tables = [];
    $table_names = [];
    foreach ($entities as $entity) {
      $entity_context = $context + ['plan_entity' => $entity];
      // Scope by source type as well as ID: plan and cluster IDs can overlap.
      $entity_context['attachments'] = $this->getAttachmentsForEntities([$entity]) ?? [];
      foreach ($this->filterAttachmentPrototypesByEntityRefCodes($prototypes, [$entity->getEntityTypeRefCode()]) as $prototype) {
        $configuration = $this->tableConfigBuilder->build($prototype, $plan);
        $item = $this->getItemTypePluginForColumn($configuration, $entity_context);
        $table = $item->getRenderArray();
        if (empty($table['#rows'])) {
          continue;
        }
        $prototype_id = $prototype->id();
        if (!isset($tables[$prototype_id])) {
          $table_names[$prototype_id] = $prototype->getName();
          $tables[$prototype_id] = [
            'header' => array_merge($this->getFullLogframeContextHeader($plan), [(string) $this->t('Attachment reference', [], $t_options)], $table['#header']),
            'rows' => [],
          ];
        }
        foreach ($table['#rows'] as $row) {
          $row['data'] = array_merge($this->getFullLogframeEntityContext($entity), [$row['data-attachment-custom-id'] ?? NULL], $row['data']);
          $tables[$prototype_id]['rows'][] = $row;
        }
      }
    }

    $framework_label = (string) $this->t('Logical framework', [], $t_options);
    $data = [$framework_label => $this->buildFullLogicalFramework($entities, $api_plan, $plan)];
    $used_names = [mb_strtolower($framework_label), 'meta data'];
    foreach ($tables as $prototype_id => $table) {
      if ($prototypes[$prototype_id]->isIndicator()) {
        $this->addCalculationMethodColumnToExcelData($table, $t_options);
      }
      $this->processSparklineChartInExcelData($table, $t_options);
      $name = $this->getFullLogframeSheetName($table_names[$prototype_id], $used_names);
      $used_names[] = mb_strtolower($name);
      $data[$name] = $table;
    }
    return $data;
  }

  /**
   * Includes the selected cluster and its direct and indirect descendants.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   All entities in the plan.
   * @param int $cluster_id
   *   The cluster's source ID.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[]
   *   The selected entities.
   */
  private function filterFullLogframeEntitiesForCluster(array $entities, int $cluster_id): array {
    $selected = [];
    $selected_plan_ids = [];
    do {
      $previous_count = count($selected);
      foreach ($entities as $key => $entity) {
        if (isset($selected[$key])) {
          continue;
        }
        if ($entity instanceof GoverningEntity && $entity->id() == $cluster_id) {
          $selected[$key] = $entity;
        }
        elseif ($entity instanceof PlanEntity) {
          $cluster = $entity->getParentGoverningEntity();
          $belongs_to_cluster = $cluster ? $cluster->id() == $cluster_id : array_intersect_key($entity->getPlanEntityParents(), $selected_plan_ids);
          if ($belongs_to_cluster) {
            $selected[$key] = $entity;
            $selected_plan_ids[$entity->id()] = TRUE;
          }
        }
      }
    } while (count($selected) > $previous_count);
    return array_intersect_key($entities, $selected);
  }

  /**
   * Gets the common context columns for all prototype worksheets.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan providing language and cluster terminology.
   *
   * @return array
   *   The translated column headers.
   */
  private function getFullLogframeContextHeader(Plan $plan): array {
    $options = ['langcode' => $plan->getPlanLanguage()];
    return [
      (string) $this->t('Entity type', [], $options),
      (string) $this->t('Entity reference', [], $options),
      (string) $this->t('Entity description', [], $options),
      (string) ($plan->getPlanClusterType() == Plan::CLUSTER_TYPE_SECTOR ? $this->t('Sector', [], $options) : $this->t('Cluster', [], $options)),
    ];
  }

  /**
   * Gets the row context for an entity independently of display settings.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The source entity.
   *
   * @return array
   *   Entity type, reference, description and cluster name.
   */
  private function getFullLogframeEntityContext(PlanEntityInterface $entity): array {
    $cluster = $this->getFullLogframeCluster($entity);
    return [
      $entity->getEntityTypeRefCode(),
      $this->getPlanEntityId($entity, ['id_type' => 'composed_reference']),
      $entity->getDescription(),
      $cluster?->getDisplayName(),
    ];
  }

  /**
   * Resolves a cluster across any number of parent levels.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The source entity.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\GoverningEntity|null
   *   The cluster, if available.
   */
  private function getFullLogframeCluster(PlanEntityInterface $entity): ?GoverningEntity {
    if ($entity instanceof GoverningEntity) {
      return $entity;
    }
    $parents = [$entity];
    $seen = [];
    while ($parent = array_shift($parents)) {
      if (!$parent instanceof PlanEntity || isset($seen[$parent->id()])) {
        continue;
      }
      $seen[$parent->id()] = TRUE;
      if ($cluster = $parent->getParentGoverningEntity()) {
        return $cluster;
      }
      $parents = array_merge($parents, array_values($parent->getPlanEntityParents()));
    }
    return NULL;
  }

  /**
   * Builds a hierarchy sheet with one row per entity-parent relationship.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   The entities in the requested scope.
   * @param \Drupal\ghi_plans\ApiObjects\Plan|null $api_plan
   *   The root plan, if available.
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The local plan object.
   *
   * @return array
   *   Header and rows, including ancestors explaining cluster alignments.
   */
  private function buildFullLogicalFramework(array $entities, ?ApiObjectsPlan $api_plan, Plan $plan): array {
    $options = ['langcode' => $plan->getPlanLanguage()];
    $table = [
      'header' => array_merge($this->getFullLogframeContextHeader($plan), [
        (string) $this->t('Parent type', [], $options),
        (string) $this->t('Parent reference', [], $options),
        (string) $this->t('Parent description', [], $options),
      ]),
      'rows' => [],
    ];
    $seen = [];
    while ($entity = array_shift($entities)) {
      $key = $entity->getEntityType() . ':' . $entity->id();
      if (isset($seen[$key])) {
        continue;
      }
      $seen[$key] = TRUE;
      $parents = $entity instanceof PlanEntity ? array_values($entity->getPlanEntityParents()) : [];
      if ($entity instanceof PlanEntity && $cluster = $entity->getParentGoverningEntity()) {
        $parents[] = $cluster;
      }
      if (empty($parents) && !$entity instanceof ApiObjectsPlan && $api_plan) {
        $parents[] = $api_plan;
      }
      $entities = array_merge($entities, $parents);
      // Ancestors explain alignment only; their attachments stay out of a
      // cluster export unless the ancestor itself belongs to that cluster.
      foreach ($parents ?: [NULL] as $parent) {
        $parent_context = $parent ? array_slice($this->getFullLogframeEntityContext($parent), 0, 3) : [NULL, NULL, NULL];
        $table['rows'][] = array_merge($this->getFullLogframeEntityContext($entity), $parent_context);
      }
    }
    return $table;
  }

  /**
   * Creates a worksheet name unique after Excel sanitization and truncation.
   *
   * @param string $label
   *   The prototype's download label.
   * @param string[] $used_names
   *   Lowercase worksheet names already reserved in this workbook.
   *
   * @return string
   *   A valid, unique Excel worksheet name.
   */
  private function getFullLogframeSheetName(string $label, array $used_names): string {
    $base = trim(Excel::sanitizeSheetTitle($label), "'") ?: (string) $this->t('Data');
    $name = $base;
    $index = 2;
    while (in_array(mb_strtolower($name), $used_names, TRUE)) {
      $suffix = ' (' . $index++ . ')';
      $name = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
    }
    return $name;
  }

  /**
   * Add a calculation method column to the excel data.
   *
   * @param array $data
   *   The table data array with the keys 'header' and 'rows'.
   * @param array $t_options
   *   An array of options for the translation service.
   */
  private function addCalculationMethodColumnToExcelData(&$data, $t_options) {
    $header = &$data['header'];
    $rows = &$data['rows'];

    // Find the position of the unit column if it's present.
    $unit_columns = array_filter($header, function ($cell) {
      return is_array($cell) && $cell['data-column-type'] == 'unit';
    });
    $unit_column_pos = array_keys($unit_columns)[0] ?? NULL;
    if ($unit_column_pos === NULL) {
      return;
    }

    // Add the column to the header after the unit column if not done yet.
    $column_label = (string) $this->t('Calculation method', [], $t_options);
    if (!in_array($column_label, $header) && $unit_column_pos !== NULL) {
      $header = ArrayHelper::insertItem($header, $unit_column_pos + 1, $column_label);
    }
    // Add the value for the new column to each row.
    foreach ($rows as &$row) {
      if (count($header) == count($row['data'])) {
        continue;
      }
      $row['data'] = ArrayHelper::insertItem($row['data'], $unit_column_pos + 1, $row['data-attachment-calculation-method']);
    }
  }

  /**
   * Process columns of type sparkline chart in the excel data.
   *
   * We want to turn the single-column representation of a spark line chart
   * into a set of monitoring period columns.
   *
   * @param array $data
   *   The table data array with the keys 'header' and 'rows'.
   * @param array $t_options
   *   An array of options for the translation service.
   */
  private function processSparklineChartInExcelData(&$data, $t_options) {
    $header = &$data['header'];
    $rows = &$data['rows'];

    // Find the position of the chart columns if present.
    $chart_columns = array_filter($header, function ($cell) {
      return is_array($cell) && $cell['data-column-type'] == 'chart';
    });
    $col_offset = 0;

    // For each chart, adjust the headers and rows.
    foreach (array_keys($chart_columns) as $chart_column_pos) {
      $original_col_index = $col_offset + $chart_column_pos;

      // Load a single attachment, just so we can use it to format the
      // monitoring periods.
      $attachment_id = $rows[0]['data-attachment-id'];
      /** @var \Drupal\ghi_plans\Plugin\FabricQuery\AttachmentQuery $query */
      $query = $this->getQueryHandler('attachment');
      $attachment = $query->getAttachment($attachment_id);
      if (!$attachment instanceof Attachment) {
        continue;
      }

      // Get all reporting period ids from all rows. We might have to handle
      // the case that not all chart data points have values for all the
      // monitoring periods.
      $reporting_period_ids = [];
      foreach ($rows as $row) {
        $reporting_period_ids = array_unique(array_merge($reporting_period_ids, $row['data'][$chart_column_pos]['data']['#reporting_period_ids'] ?? []));
      }
      $original_label = $header[$original_col_index]['data'];

      // Iterate over all unique monitoring periods.
      foreach ($reporting_period_ids as $reporting_period_id) {
        // Get the original label of the chart column.
        $column_label = $attachment->formatMonitoringPeriod('text', $reporting_period_id, $original_label . ': @date_range');
        // Add a new column for the current monitoring period.
        if (!in_array($column_label, $header)) {
          $header = ArrayHelper::insertItem($header, $col_offset + $chart_column_pos + 1, $column_label);
        }
        // Iterate over all rows to add a column for the current monitoring
        // period.
        foreach ($rows as &$row) {
          if (count($header) == count($row['data'])) {
            continue;
          }
          $reporting_period_value = $row['data'][$original_col_index]['data']['#data'][$reporting_period_id] ?? NULL;
          $col_value = [
            'data-value' => $reporting_period_value,
            'data-raw-value' => $reporting_period_value,
            'data-sort-type' => 'numeric',
            'data-column-type' => 'amount',
            'data-content' => $column_label,
          ];
          $row['data'] = ArrayHelper::insertItem($row['data'], $col_offset + $chart_column_pos + 1, $col_value);
        }
        $col_offset++;
      }

      // Remove the column holding the original single-column value, as we
      // don't need that anymore.
      unset($header[$original_col_index]);
      $header = array_values($header);
      foreach ($rows as &$row) {
        unset($row['data'][$original_col_index]);
        $row['data'] = array_values($row['data']);
      }
    }
  }

  /**
   * Build the worksheet data for the logical framework.
   *
   * @param array $entities
   *   The entities to include.
   * @param array $cluster_args
   *   An optional array of arguments for translated strings.
   * @param array $t_options
   *   An optional array of options for translated strings.
   *
   * @return array|null
   *   Either NULL, if no logframe can be build, or an array to be used for
   *   Excel exports.
   */
  private function buildLogicalFrameworkExcelsheet(array $entities, ?array $cluster_args = [], ?array $t_options = []): ?array {
    $conf = $this->configuration;

    $entity_parents = [];
    $entity_clusters = [];
    foreach ($entities as $entity) {
      $entity_parents[$entity->id()] = $entity instanceof PlanEntity ? $this->getEntityAlignments($entity) : [];
      $entity_clusters[$entity->id()] = $entity instanceof PlanEntity ? $entity->getParentGoverningEntity() : NULL;
      foreach ($entity_parents[$entity->id()] as $parent) {
        $entity_clusters[$parent->id()] = $parent instanceof PlanEntity ? $parent->getParentGoverningEntity() : NULL;
      }
    }
    if (empty(array_filter($entity_parents))) {
      return NULL;
    }

    $logical_framework = [];
    foreach ($entities as $entity) {
      $parents = $entity_parents[$entity->id()];

      if (empty($logical_framework['header'])) {
        $header = [];
        foreach ($parents as $parent) {
          $parent_ref_code = $parent->getEntityTypeRefCode();
          if (array_key_exists($parent_ref_code, $header)) {
            continue;
          }
          if (!empty($entity_clusters[$parent->id()])) {
            $header[] = (string) $this->t('@cluster_label abbreviation', $cluster_args, $t_options);
            $header[] = (string) $this->t('@cluster_label name', $cluster_args, $t_options);
          }
          $header[$parent_ref_code] = (string) $this->t('@ref_code code', [
            '@ref_code' => $parent->getEntityTypeRefCode(),
          ], $t_options);
          $header[$parent_ref_code . '_description'] = (string) $this->t('@ref_code description', [
            '@ref_code' => $parent->getEntityTypeRefCode(),
          ], $t_options);
        }

        if (!empty($entity_clusters[$entity->id()])) {
          $header[] = (string) $this->t('@cluster_label abbreviation', $cluster_args, $t_options);
          $header[] = (string) $this->t('@cluster_label name', $cluster_args, $t_options);
        }

        $header[] = (string) $this->t('@ref_code code', [
          '@ref_code' => $conf['entities']['entity_ref_code'],
        ], $t_options);
        $header[] = (string) $this->t('@ref_code description', [
          '@ref_code' => $conf['entities']['entity_ref_code'],
        ], $t_options);
        $logical_framework['header'] = array_values($header);
      }
    }

    // Build the content for the logframe sheet.
    foreach ($entities as $entity) {
      $parents = $entity_parents[$entity->id()];
      $alignment_paths = $this->getEntityAlignmentsPaths($entity);
      foreach ($alignment_paths as $parent_ids) {
        $logical_framework_row = [];
        foreach ($parent_ids as $parent_id) {
          $parent = $parents[$parent_id];
          if (!empty($entity_clusters[$parent->id()])) {
            $governing_entity = $entity_clusters[$parent->id()];
            $logical_framework_row[] = $governing_entity->getCustomName('custom_id');
            $logical_framework_row[] = $governing_entity->getName();
          }
          $logical_framework_row[] = $this->getPlanEntityId($parent, $conf['entities']);
          $logical_framework_row[] = $parent->getDescription();
        }

        if (!empty($entity_clusters[$entity->id()])) {
          $governing_entity = $entity_clusters[$entity->id()];
          $logical_framework_row[] = $governing_entity->getCustomName('custom_id');
          $logical_framework_row[] = $governing_entity->getName();
        }

        $logical_framework_row[] = $this->getPlanEntityId($entity, $conf['entities']);
        $logical_framework_row[] = $entity->getDescription();
        $logical_framework['rows'][] = $logical_framework_row;
      }

    }
    return $logical_framework;
  }

}
