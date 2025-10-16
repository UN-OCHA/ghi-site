<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurationUpdateInterface;
use Drupal\ghi_blocks\Interfaces\CustomLinkBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\AttachmentTable;
use Drupal\ghi_blocks\Traits\AttachmentTableTrait;
use Drupal\ghi_blocks\Traits\ConfigValidationTrait;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface;
use Drupal\ghi_plans\ApiObjects\Entities\PlanEntity;
use Drupal\ghi_plans\ApiObjects\Plan as ApiObjectsPlan;
use Drupal\ghi_plans\ApiObjects\PlanEntityInterface;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\AttachmentHelper;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\LogframeSubpage;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\ArrayHelper;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelMultipleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanEntityLogframe' block.
 *
 * @Block(
 *  id = "plan_entity_logframe",
 *  admin_label = @Translation("Entity Logframe"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "entity" = "entity_query",
 *    "attachment" = "attachment_query",
 *    "attachment_search" = "attachment_search_query",
 *    "attachment_prototype" = "plan_attachment_prototype_query",
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" }),
 *    "plan_cluster" = @ContextDefinition("entity:base_object", label = @Translation("Cluster"), constraints = { "Bundle": "governing_entity" }, required =  FALSE)
 *  },
 *  config_forms = {
 *    "entities" = {
 *      "title" = @Translation("Entities"),
 *      "callback" = "entitiesForm"
 *    },
 *    "tables" = {
 *      "title" = @Translation("Tables"),
 *      "callback" = "tablesForm",
 *      "base_form" = TRUE
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanEntityLogframe extends GHIBlockBase implements MultiStepFormBlockInterface, ConfigurableTableBlockInterface, OverrideDefaultTitleBlockInterface, CustomLinkBlockInterface, TrustedCallbackInterface, ConfigValidationInterface, ConfigurationUpdateInterface, HPCDownloadExcelMultipleInterface {

  use ConfigurationContainerTrait;
  use AttachmentTableTrait;
  use CustomLinkTrait;
  use ConfigValidationTrait;

  /**
   * The logframe manager.
   *
   * @var \Drupal\ghi_subpages\LogframeManager
   */
  public $logframeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Set our own properties.
    $instance->logframeManager = $container->get('ghi_subpages.logframe_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    $first_entity = !empty($entities) ? reset($entities) : NULL;
    if (!$first_entity) {
      return NULL;
    }
    if ($first_entity instanceof ApiObjectsPlan) {
      return $this->t('Response plan', [], ['langcode' => $this->getCurrentPlanObject()?->getPlanLanguage()]);
    }
    return count($entities) > 1 ? $first_entity->plural_name : $first_entity->singular_name;
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

    // Assemble the list.
    $rendered_items = [];
    foreach ($entities as $entity) {
      if ($this->isConfigurationPreview() || !$lazy_load) {
        $tables = $this->buildTablesContainer($entity, $conf['tables']);
      }
      else {
        $tables = [
          '#lazy_builder' => [
            static::class . '::lazyBuildTables',
            [
              $this->getPluginId(),
              $this->getUuid(),
              $this->getPageNode()?->toUrl()?->toString() ?? $this->getCurrentUri(),
              $entity->id(),
            ],
          ],
          '#create_placeholder' => TRUE,
          '#cache' => [
            'context' => $this->getCacheContexts(),
          ],
        ];
      }
      $contributes_heading = $entity instanceof PlanEntity ? $this->buildContributesToHeading($entity) : NULL;
      $entity_id = $this->getPlanEntityId($entity, $conf['entities']);
      $entity_description = $this->getPlanEntityDescription($entity, $conf['entities']);
      $rendered_items[] = [
        'label' => $entity_id,
        'description' => $entity_description,
        'contributes_heading' => $contributes_heading,
        'attachment_tables' => $tables ?: NULL,
      ];
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
    if ($link) {
      $build['links'][] = $link->toRenderable();
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    $data = [];

    // Get the entities to render.
    $entities = $this->getRenderableEntities();
    if (empty($entities)) {
      return;
    }

    // Get the config.
    $conf = $this->getBlockConfig();

    // Sort the entities.
    $this->sortPlanEntities($entities, $conf['entities']);

    // Prepare the sheet for the logical framework.
    $logframe_sheet_label = (string) $this->t('Logical framework');
    $data[$logframe_sheet_label] = [];
    $logical_framework = [
      'header' => [],
      'rows' => [],
    ];

    $plan = $this->getCurrentPlanObject();
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

    // Build the actual data tables if applicable, one for each configured
    // table.
    foreach ($entities as $entity) {
      $tables = $this->buildTables($entity, $conf['tables']);
      foreach ($tables as $key => $table) {
        /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $prototype */
        $prototype = $table['#prototype'];
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
            $additional_columns[] = $entity_cluster_alignments[$entity->id()]->getEntityName();
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
   * Check if there is download data for this element.
   *
   * @return bool
   *   TRUE if there is something to download, FALSE otherwise.
   */
  private function hasDownloadData(): bool {
    $conf = $this->getBlockConfig();
    if (empty($conf['entities']['entity_ref_code'])) {
      return FALSE;
    }

    $entities = $this->getRenderableEntities();
    if (empty($entities)) {
      return FALSE;
    }

    if ($this->buildLogicalFrameworkExcelsheet($entities)) {
      return TRUE;
    }

    foreach ($entities as $entity) {
      $tables = $this->buildTables($entity, $conf['tables']);
      foreach ($tables as $table) {
        if (!empty($table['#rows'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
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
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery $query */
      $query = $this->getQueryHandler('attachment');
      $attachment = $query->getAttachment($attachment_id);
      if (!$attachment instanceof DataAttachment) {
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
   * Get the formatted plan entity id according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   *
   * @return string
   *   The formatted plan entity id.
   */
  private function getPlanEntityId(PlanEntityInterface $entity, array $conf) {
    $id_type = $conf['id_type'] ?? 'custom_id';
    return $entity instanceof ApiObjectsPlan ? $entity->getPlanTypeAbbreviation() : $entity->getCustomName($id_type);
  }

  /**
   * Get the formatted plan entity description according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   * @param bool $truncate_description
   *   Whether to truncate the description or not.
   *
   * @return string
   *   The formatted plan entity description.
   */
  private function getPlanEntityDescription(PlanEntityInterface $entity, array $conf, $truncate_description = FALSE) {
    $description = $entity->getDescription();
    return $truncate_description ? Unicode::truncate($description, 120, TRUE, TRUE) : $description;
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
    $conf = $this->getBlockConfig();

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
          $parent_ref_code = $parent->ref_code;
          if (array_key_exists($parent_ref_code, $header)) {
            continue;
          }
          if (!empty($entity_clusters[$parent->id()])) {
            $header[] = (string) $this->t('@cluster_label abbreviation', $cluster_args, $t_options);
            $header[] = (string) $this->t('@cluster_label name', $cluster_args, $t_options);
          }
          $header[$parent_ref_code] = (string) $this->t('@ref_code code', [
            '@ref_code' => $parent->ref_code,
          ], $t_options);
          $header[$parent_ref_code . '_description'] = (string) $this->t('@ref_code description', [
            '@ref_code' => $parent->ref_code,
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

  /**
   * Get the entity attachment tables according to the configuration.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface $entity
   *   The plan entity.
   * @param array $conf
   *   The entity configuration.
   *
   * @return array
   *   An array of entity attachment tables.
   */
  public function buildTables(PlanEntityInterface $entity, array $conf) {
    $tables = [];
    if (empty($conf['attachment_tables'])) {
      return $tables;
    }

    $attachments = $this->getAttachmentsForEntities([$entity]);
    $attachment_prototypes = $this->getAttachmentPrototypes();

    $context = $this->getBlockContext();
    $context['attachments'] = $attachments;
    $context['plan_entity'] = $entity;

    foreach ($conf['attachment_tables'] as $table_configuration) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($table_configuration, $context);
      if (!$item_type instanceof AttachmentTable) {
        continue;
      }
      if (!array_key_exists($item_type->get('attachment_prototype'), $attachment_prototypes)) {
        continue;
      }
      $table = $item_type->getRenderArray();
      if (!$table || empty($table['#rows'])) {
        continue;
      }
      $attachment_prototype = $attachment_prototypes[$item_type->get('attachment_prototype')];
      $tables[$attachment_prototype->id()] = $table;
    }
    return $tables;
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
   * Lazy builder callback for attachment tables.
   *
   * @param string $plugin_id
   *   The plugin id of this block plugin.
   * @param string $block_uuid
   *   The uuid of this block plugins instance.
   * @param string $uri
   *   The current page uri.
   * @param int $entity_id
   *   The id of the entity for which the tables should be rendered.
   *
   * @return array
   *   A render array representing the tables.
   */
  public static function lazyBuildTables($plugin_id, $block_uuid, $uri, $entity_id) {
    /** @var \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityLogframe $block_instance */
    $block_instance = BlockHelper::getBlockInstance($uri, $plugin_id, $block_uuid);
    if (!$block_instance) {
      return [];
    }
    $entities = $block_instance->getRenderableEntities();
    if (!array_key_exists($entity_id, $entities)) {
      return [];
    }
    $tables = $block_instance->buildTablesContainer($entities[$entity_id], $block_instance->getBlockConfig()['tables']);

    // Reset the static caches to prevent memory issues. Lazy load callbacks
    // are part of the same main thread that renders the page. Given that there
    // can be an arbitrarily high number of these calls, especially on logframe
    // pages, we need to account for that by keeping memory under control. So
    // better to loose a bit of performance when it comes to lazy loading the
    // tables, than running into a memory issue and not showing some of the
    // tables at all.
    drupal_static_reset();

    return $tables ?: [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => t('There is no caseload or indicator available', [], ['langcode' => $block_instance->getCurrentPlanObject()?->getPlanLanguage() ?? 'en']),
      '#attributes' => [
        'class' => ['empty-message'],
      ],
    ];
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
    $ajax_parents = array_merge($form['#array_parents']);
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
            'description' => $this->getPlanEntityDescription($entity, $defaults, TRUE),
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
          'id_' . EndpointQuery::SORT_ASC => $this->t('ID (asc)'),
          'id_' . EndpointQuery::SORT_DESC => $this->t('ID (desc)'),
          'description_' . EndpointQuery::SORT_ASC => $this->t('Description (asc)'),
          'description_' . EndpointQuery::SORT_DESC => $this->t('Description (desc)'),
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
          $form['entity_ids'][$entity->id]['#disabled'] = TRUE;
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
    return $this->logframeManager->getEntityTypesFromPlanObject($this->getCurrentPlanObject());
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
      /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\EntityQuery $query */
      $query = $this->getQueryHandler('entity');
      $plan_data = $query->getEntity('plan', $context_object->getSourceId());
      return $plan_data ? [
        $plan_data->id() => $plan_data,
      ] : [];
    }

    $filter = NULL;
    if ($entity_ref_code) {
      $filter = ['ref_code' => $entity_ref_code];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    $entities = $query->getPlanEntities($context_object, NULL, $filter);
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
      uasort($entities, function ($a, $b) use ($key, $sort, $conf) {
        $a_value = $key == 'id' ? $this->getPlanEntityId($a, $conf) : (!empty(($a)->{$key}) ? ($a)->{$key} : 0);
        $b_value = $key == 'id' ? $this->getPlanEntityId($b, $conf) : (!empty(($b)->{$key}) ? ($b)->{$key} : 0);
        if ($sort == EndpointQuery::SORT_ASC) {
          return strnatcmp($a_value, $b_value);
        }
        if ($sort == EndpointQuery::SORT_DESC) {
          return strnatcmp($b_value, $a_value);
        }
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
   * Get attachments for the given set of entities.
   *
   * @param \Drupal\ghi_plans\ApiObjects\PlanEntityInterface[] $entities
   *   The plan entity objects.
   * @param int $prototype_id
   *   An optional prototype id to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment[]
   *   An array of data attachments.
   */
  public function getAttachmentsForEntities(array $entities, $prototype_id = NULL) {
    if (empty($entities)) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery $query */
    $query = $this->endpointQueryManager->createInstance('attachment_search_query');
    $attachments = $query->getAttachmentsForEntities($entities);

    // Filter out non-data attachments.
    $attachments = array_filter($attachments, function ($attachment) use ($prototype_id) {
      if (!$attachment instanceof DataAttachment) {
        return FALSE;
      }
      if ($prototype_id && $prototype_id == $attachment->getPrototype()->id()) {
        return FALSE;
      }
      return TRUE;
    });
    return $attachments;
  }

  /**
   * Get the available attachment prototypes for the current plan context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype[]
   *   An array of attachment prototypes.
   */
  private function getAttachmentPrototypes() {
    $plan_object = $this->getCurrentPlanObject();
    if (!$plan_object) {
      return [];
    }
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanAttachmentPrototypeQuery $query */
    $query = $this->endpointQueryManager->createInstance('plan_attachment_prototype_query');
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
  public static function trustedCallbacks() {
    return [
      'lazyBuildTables',
    ];
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
