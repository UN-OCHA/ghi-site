<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanGoverningEntitiesTable' block.
 *
 * @Block(
 *  id = "plan_governing_entities_table",
 *  admin_label = @Translation("Governing Entities Overview Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "cluster_summary" = "plan_funding_cluster_query",
 *  },
 *  default_title = @Translation("Cluster overview"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "base" = {
 *      "title" = @Translation("Base settings"),
 *      "callback" = "baseForm",
 *      "base_form" = TRUE
 *    },
 *    "table" = {
 *      "title" = @Translation("Table columns"),
 *      "callback" = "tableForm"
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class PlanGoverningEntitiesTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use ConfigurationContainerTrait;
  use ConfigurationItemClusterRestrictTrait;
  use TableSoftLimitTrait;

  /**
   * The section manager.
   *
   * @var \Drupal\ghi_subpages\SubpageManager
   */
  protected $subpageManager;

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
  public function buildContent() {
    $table_data = $this->buildTableData();
    if (empty($table_data)) {
      return NULL;
    }
    return [
      '#theme' => 'table',
      '#header' => $table_data['header'],
      '#rows' => $table_data['rows'],
      '#sortable' => TRUE,
      '#soft_limit' => $this->getBlockConfig()['display']['soft_limit'] ?? 0,
      '#progress_groups' => TRUE,
      '#block_id' => $this->getBlockId(),
    ];
  }

  /**
   * Build the table data for this element.
   *
   * @return array
   *   An array with the keys "header" and "rows".
   */
  private function buildTableData() {
    $conf = $this->getBlockConfig();

    $columns = $this->getConfiguredItems($conf['table']['columns']);
    $entities = $this->getEntityObjects();

    if (empty($columns) | empty($entities)) {
      return NULL;
    }

    if (!empty($conf['base']['cluster_restrict'])) {
      // Filter the entities according to the configuration.
      $entities = $this->applyClusterRestrictFilterToEntities($entities, $conf['base']['cluster_restrict']);
    }
    if (empty($entities)) {
      return NULL;
    }

    $objects = $this->loadBaseObjectsForEntities($entities);
    if (empty($objects)) {
      return NULL;
    }

    $context = $this->getBlockContext();

    $header = $this->buildTableHeader($columns);

    // Sort the entites by name.
    usort($entities, function ($a, $b) {
      return strnatcasecmp($a->getEntityName(), $b->getEntityName());
    });

    $rows = [];
    foreach ($entities as $entity) {
      $base_object = $objects[$entity->id] ?? NULL;
      if (!$base_object) {
        continue;
      }

      // Set the context.
      $subpage_node = $this->subpageManager->loadSubpageForBaseObject($base_object);

      $context['base_object'] = $base_object;
      $context['context_node'] = $subpage_node;
      $context['entity'] = $entity;

      $row = [];
      $skip_row = FALSE;
      foreach ($columns as $key => $column) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($column, $context);

        $progress_group = NULL;
        if ($item_type->getColumnType() == 'percentage') {
          $progress_group = 'coverage';
        }
        elseif ($item_type->getColumnType() == 'amount') {
          $progress_group = 'amount-' . $key;
        }

        // Then add the value to the row.
        $cell = $item_type->getTableCell();
        $cell['data-progress-group'] = $progress_group;
        $row[] = $cell;

        // Update the skip row flag. Make it lazy, only check the item type if
        // it still makes a difference.
        $skip_row = $skip_row ? $skip_row : ($skip_row || $item_type->checkFilter() === FALSE);
      }

      // See if filtering needs to be applied.
      if ($skip_row) {
        continue;
      }

      $rows[] = $row;
    }

    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanClusterSummaryQuery $cluster_query */
    $cluster_query = $this->getQueryHandler('cluster_summary');

    // If configured accordingly, add a "Cluster not specified row".
    if (!empty($conf['base']['include_cluster_not_reported']) && $conf['base']['include_cluster_not_reported']) {

      $not_specified_entity = $cluster_query->getNotSpecifiedCluster();

      if ($not_specified_entity && !empty($not_specified_entity->total_funding)) {
        $context['base_object'] = NULL;
        $context['context_node'] = NULL;
        $context['raw_data'] = $not_specified_entity;

        $row = [];
        foreach ($columns as $column) {
          /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
          $item_type = $this->getItemTypePluginForColumn($column, $context);

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
            $row[] = $item_type->getTableCell();
          }
          else {
            $row[] = [
              'data' => $this->t('N/A'),
              'data-value' => 0,
              'data-raw-value' => 0,
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

    if (!empty($conf['base']['include_shared_funding']) && $conf['base']['include_shared_funding'] && $cluster_query->hasSharedFunding()) {
      $context['base_object'] = NULL;
      $context['context_node'] = NULL;
      $context['raw_data'] = (object) [
        'total_funding' => $cluster_query->getSharedFunding(),
      ];

      $row = [];
      foreach ($columns as $column) {
        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($column, $context);

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
          $row[] = $item_type->getTableCell();
        }
        else {
          $row[] = [
            'data' => $this->t('N/A'),
            'data-value' => 0,
            'data-raw-value' => 0,
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
    return [
      'header' => $header,
      'rows' => $rows,
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
      'base' => [
        'include_cluster_not_reported' => FALSE,
        'include_shared_funding' => FALSE,
        'hide_target_values_for_projects' => FALSE,
        'cluster_restrict' => [],
      ],
      'table' => [
        'columns' => [],
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
    return 'base';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'base';
  }

  /**
   * Form callback for the base settings form.
   */
  public function baseForm(array $form, FormStateInterface $form_state) {

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

    $form['hide_target_values_for_projects'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide target values for projects'),
      '#description' => $this->t('Check this if you want to hide the target values from the project details popover.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'hide_target_values_for_projects'),
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
      $default_value = [
        [
          'item_type' => 'entity_name',
          'config' => [
            'label' => $this->getGenericEntityName(),
          ],
        ],
      ];
    }
    $form['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured table columns'),
      '#title_display' => 'invisible',
      '#item_type_label' => $this->t('Column'),
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
        ],
      ],
      '#element_context' => $this->getBlockContext(),
      '#row_filter' => TRUE,
    ];
    return $form;
  }

  /**
   * Form callback for the display configuration form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    $form['soft_limit'] = $this->buildSoftLimitFormElement($this->getDefaultFormValueFromFormState($form_state, 'soft_limit'));
    return $form;
  }

  /**
   * Get all governing entity objects for the current block instance.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Entities\EntityObjectInterface[]|null
   *   An array of entity objects, aka clusters or NULL.
   */
  private function getEntityObjects() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery $query */
    $query = $this->getQueryHandler('entities');
    return $query->getPlanEntities($this->getPageNode(), 'governing');
  }

  /**
   * Load the nodes associated to the entities.
   *
   * @param array $entities
   *   The entity objects.
   *
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface[]
   *   An array of node objects.
   */
  private function loadBaseObjectsForEntities(array $entities) {
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);

    return BaseObjectHelper::getBaseObjectsFromOriginalIds($entity_ids, 'governing_entity');
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
    $plan_structure = PlanStructureHelper::getRpmPlanStructure($context['plan_object']);
    $first_gve = !empty($plan_structure['governing_entities']) ? reset($plan_structure['governing_entities']) : NULL;
    return $first_gve ? $first_gve->label_singular : $this->t('Cluster');
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getFirstEntityObject(),
      'context_node' => $this->getFirstEntityObject(),
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
    return $this->buildTableData();
  }

}
