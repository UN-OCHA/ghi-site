<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\Router;
use Drupal\ghi_base_objects\Helpers\BaseObjectHelper;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanGoverningEntitiesTable' block.
 *
 * @Block(
 *  id = "plan_governing_entities_table",
 *  admin_label = @Translation("Governing Entities Overview Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = {
 *      "service" = "ghi_plans.plan_entities_query"
 *    },
 *    "cluster_summary" = {
 *      "service" = "ghi_plans.plan_cluster_summary_query"
 *    },
 *  },
 *  default_title = @Translation("Cluster overview"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class PlanGoverningEntitiesTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, SyncableBlockInterface {

  use ConfigurationContainerTrait;
  use ConfigurationItemClusterRestrictTrait;

  /**
   * The manager class for configuration container items.
   *
   * @var \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, Router $router, KeyValueFactory $keyValueFactory, EndpointQuery $endpoint_query, EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $file_system, ConfigurationContainerItemManager $configuration_container_item_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request_stack, $router, $keyValueFactory, $endpoint_query, $entity_type_manager, $file_system);

    $this->configurationContainerItemManager = $configuration_container_item_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack'),
      $container->get('router.no_access_checks'),
      $container->get('keyvalue'),
      $container->get('hpc_api.endpoint_query'),
      $container->get('entity_type.manager'),
      $container->get('file_system'),
      $container->get('plugin.manager.configuration_container_item_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $columns = [];
    // Define a transition map.
    $transition_map = [
      'governing_entity_name' => [
        'target' => 'entity_name',
      ],
      'plan_entities_counter' => [
        'target' => 'entity_counter',
        'config' => ['entity_type' => 'plan'],
      ],
      'partners_counter' => [
        'target' => 'project_data',
        'config' => ['data_type' => 'organizations_count'],
      ],
      'projects_counter' => [
        'target' => 'project_data',
        'config' => ['data_type' => 'projects_count'],
      ],
      'original_requirements' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'original_requirements'],
      ],
      'funding_requirements' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'current_requirements'],
      ],
      'total_funding' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_totals'],
      ],
      'funding_coverage' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_progress_bar'],
      ],
      'funding_gap' => [
        'target' => 'funding_data',
        'config' => ['data_type' => 'funding_gap'],
      ],
    ];
    foreach ($config->table_columns as $incoming_item) {
      $source_type = !empty($incoming_item->element) ? $incoming_item->element : NULL;
      if (!$source_type || !array_key_exists($source_type, $transition_map)) {
        continue;
      }
      // Apply generic config based on the transition map.
      $transition_definition = $transition_map[$source_type];
      $item = [
        'item_type' => $transition_definition['target'],
        'config' => [
          'label' => property_exists($incoming_item, 'label') ? $incoming_item->label : NULL,
        ],
      ];
      if (array_key_exists('config', $transition_definition)) {
        $item['config'] += $transition_definition['config'];
      }

      // Do special processing for individual item types.
      $value = property_exists($incoming_item, 'value') ? $incoming_item->value : NULL;

      switch ($transition_definition['target']) {
        case 'entity_counter':
          $item['config']['entity_prototype'] = $value;
          break;

        case 'original_requirements':
        case 'funding_requirements':
          $item['config']['scale'] = is_object($value) && property_exists($value, 'formatting') ? $value->formatting : 'auto';
          break;

        default:
          break;
      }
      $columns[] = $item;
    }
    return [
      'label' => property_exists($config, 'widget_title') ? $config->widget_title : NULL,
      'label_display' => TRUE,
      'hpc' => [
        'base' => [
          'include_cluster_not_reported' => property_exists($config, 'include_cluster_not_reported') ? $config->include_cluster_not_reported : FALSE,
          'include_shared_funding' => property_exists($config, 'include_shared_funding') ? $config->include_shared_funding : FALSE,
          'hide_target_values_for_projects' => property_exists($config, 'hide_target_values_for_projects') ? $config->hide_target_values_for_projects : FALSE,
          'cluster_restrict' => property_exists($config, 'cluster_restrict') ? [
            'type' => $config->cluster_restrict,
            'tag' => $config->cluster_tag,
          ] : NULL,
        ],
        'table' => [
          'columns' => $columns,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
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

    $header = [];
    foreach ($columns as $column) {
      /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
      $item_type = $this->getItemTypePluginForColumn($column);
      $header[] = [
        'data' => $item_type->getLabel(),
        'data-sort-type' => $item_type::SORT_TYPE,
        'data-sort-order' => count($header) == 0 ? 'ASC' : '',
        'data-column-type' => $item_type::ITEM_TYPE,
      ];
    }

    // Sort the entites by name.
    usort($entities, function ($a, $b) {
      return strnatcasecmp($a->name, $b->name);
    });

    $rows = [];
    foreach ($entities as $entity) {
      if (!array_key_exists($entity->id, $objects)) {
        continue;
      }

      // Add the entity and the node object to the context array.
      $base_object = $objects[$entity->id];
      $context['base_object'] = $base_object;
      $context['context_node'] = $base_object && $base_object->bundle() != 'plan' ? $base_object : NULL;
      $context['entity'] = $entity;

      $row = [];
      $skip_row = FALSE;
      foreach ($columns as $column) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($column, $context);

        // Then add the value to the row.
        $row[] = [
          'data' => $item_type->getRenderArray(),
          'data-value' => $item_type->getValue(),
          'data-sort-value' => $item_type->getSortableValue(),
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type::ITEM_TYPE,
          'class' => $item_type->getClasses(),
        ];

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

    // If configured accordingly, add a "Cluster not specified row".
    if (!empty($conf['base']['include_cluster_not_reported']) && $conf['base']['include_cluster_not_reported']) {
      /** @var \Drupal\ghi_plans\Query\PlanClusterSummaryQuery $query */
      $query = $this->getQueryHandler('cluster_summary');
      $not_specified_entity = $query->getNotSpecifiedCluster();

      if ($not_specified_entity && !empty($not_specified_entity->total_funding)) {
        $context['base_object'] = NULL;
        $context['context_node'] = NULL;
        $context['entity'] = $not_specified_entity;

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
              'data-sort-value' => $not_reported_label,
              'data-sort-type' => $item_type::SORT_TYPE,
              'data-column-type' => $item_type::ITEM_TYPE,
            ];
          }
          elseif ($item_type->getPluginId() == 'funding_data' && $item_type->get('data_type') == 'funding_totals') {
            // Add the funding.
            $row[] = [
              'data' => $item_type->getRenderArray(),
              'data-value' => $item_type->getValue(),
              'data-sort-value' => $item_type->getSortableValue(),
              'data-sort-type' => $item_type::SORT_TYPE,
              'data-column-type' => $item_type::ITEM_TYPE,
              'class' => $item_type->getClasses(),
            ];
          }
          else {
            $row[] = [
              'data' => $this->t('N/A'),
              'data-value' => 0,
              'data-sort-value' => 0,
              'data-sort-type' => $item_type::SORT_TYPE,
              'data-column-type' => $item_type::ITEM_TYPE,
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

    if (!empty($conf['base']['include_shared_funding']) && $conf['base']['include_shared_funding'] && $this->getQueryHandler('cluster_summary')->hasSharedFunding()) {
      $context['base_object'] = NULL;
      $context['context_node'] = NULL;
      $context['entity'] = (object) [
        'total_funding' => $this->getQueryHandler('cluster_summary')->getSharedFunding(),
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
            'data-sort-value' => $not_reported_label,
            'data-sort-type' => $item_type::SORT_TYPE,
            'data-column-type' => $item_type::ITEM_TYPE,
          ];
        }
        elseif ($item_type->getPluginId() == 'funding_data' && $item_type->get('data_type') == 'funding_totals') {
          /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\FundingData $item_type */
          $item_type->disableFtsLink();
          // Add the funding.
          $row[] = [
            'data' => $item_type->getRenderArray(),
            'data-value' => $item_type->getValue(),
            'data-sort-value' => $item_type->getSortableValue(),
            'data-sort-type' => $item_type::SORT_TYPE,
            'data-column-type' => $item_type::ITEM_TYPE,
            'class' => $item_type->getClasses(),
          ];
        }
        else {
          $row[] = [
            'data' => $this->t('N/A'),
            'data-value' => 0,
            'data-sort-value' => 0,
            'data-sort-type' => $item_type::SORT_TYPE,
            'data-column-type' => $item_type::ITEM_TYPE,
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
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
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
  public function getSubforms() {
    return [
      'base' => [
        'title' => $this->t('Base settings'),
        'callback' => 'baseForm',
        'base_form' => TRUE,
      ],
      'table' => [
        'title' => $this->t('Table columns'),
        'callback' => 'tableForm',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform() {
    $conf = $this->getBlockConfig();
    if (!empty($conf['table']) && !empty($conf['table'])) {
      return 'table';
    }
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
   * Get all governing entity objects for the current block instance.
   *
   * @return object[]
   *   An array of entity objects, aka clusters.
   */
  private function getEntityObjects() {
    /** @var \Drupal\ghi_plans\Query\PlanEntitiesQuery $query */
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
   * @return \Drupal\ghi_base_objects\Entity\BaseObjectInterface
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
    $first_gve = reset($plan_structure['governing_entities']);
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
      ],
      'entity_counter' => [
        'entity_type' => 'plan',
        'value_preview' => FALSE,
      ],
      'project_data' => [
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

}
