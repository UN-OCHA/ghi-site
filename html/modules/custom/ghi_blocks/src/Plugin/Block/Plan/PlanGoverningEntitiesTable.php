<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactory;
use Drupal\Core\Routing\Router;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemManager;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\NodeHelper;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'PlanGoverningEntitiesTable' block.
 *
 * @Block(
 *  id = "plan_governing_entities_table",
 *  admin_label = @Translation("Governing Entities Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = {
 *      "service" = "ghi_plans.plan_entities_query"
 *    },
 *    "attachment" = {
 *      "service" = "ghi_plans.attachment_query"
 *    },
 *  },
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class PlanGoverningEntitiesTable extends GHIBlockBase implements SyncableBlockInterface {

  /**
   * The manager class for configuration container items.
   *
   * @var \Drupal\ghi_form_elements\ConfigurationContainerItemManager
   */
  protected $configurationContainerItemManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RequestStack $request_stack, Router $router, KeyValueFactory $keyValueFactory, EndpointQuery $endpoint_query, EntityTypeManagerInterface $entity_type_manager, ConfigurationContainerItemManager $configuration_container_item_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $request_stack, $router, $keyValueFactory, $endpoint_query, $entity_type_manager);

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
      $container->get('plugin.manager.configuration_container_item_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config) {
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
      if (is_object($value) && property_exists($value, 'cluster_restrict') && property_exists($value, 'cluster_tag')) {
        $item['config']['cluster_restrict'] = [
          'type' => $value->cluster_restrict,
          'tag' => $value->cluster_tag,
        ];
      }
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
        'columns' => $columns,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $conf = $this->getBlockConfig();

    if (empty($conf['columns'])) {
      return NULL;
    }

    $allowed_items = $this->getAllowedItemTypes();
    $columns = array_filter($conf['columns'], function ($column) use ($allowed_items) {
      return array_key_exists($column['item_type'], $allowed_items);
    });
    if (empty($columns)) {
      return;
    }

    $context = $this->getBlockContext();

    $header = [];
    foreach ($columns as $column) {
      $item_type = $this->configurationContainerItemManager->createInstance($column['item_type'], $allowed_items[$column['item_type']]);
      $item_type->setContext($context);
      $item_type->setConfig($column['config']);
      $header[] = [
        'data' => $item_type->getLabel(),
        'data-sort-type' => $item_type::SORT_TYPE,
        'data-sort-order' => count($header) == 0 ? 'ASC' : '',
        'data-column-type' => $item_type::ITEM_TYPE,
      ];
    }

    $entities = $this->getEntityObjects();
    $nodes = $this->loadNodesForEntities($entities);

    $rows = [];
    foreach ($entities as $entity) {
      if (!array_key_exists($entity->id, $nodes)) {
        continue;
      }

      // Add the entity and the node object to the context array.
      $context['context_node'] = $nodes[$entity->id];
      $context['entity'] = $entity;

      $row = [];
      $skip_row = FALSE;
      foreach ($columns as $column) {
        if (!array_key_exists($column['item_type'], $allowed_items)) {
          continue;
        }

        // Get an instance of the item type plugin for this column, set it's
        // config and the context.
        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->configurationContainerItemManager->createInstance($column['item_type'], $allowed_items[$column['item_type']]);
        $item_type->setConfig($column['config']);
        $item_type->setContext($context);

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

    // @todo Make this work with an arbitrary setup. Currently it only works
    // for the entity name as first column.
    usort($rows, function ($a, $b) {
      return strnatcasecmp($a[0]['data-sort-value'], $b[0]['data-sort-value']);
    });

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
      'columns' => [],

    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $default_value = $this->getDefaultFormValueFromFormState($form_state, 'columns');
    if (empty($default_value)) {
      $default_value = [
        [
          'item_type' => 'entity_name',
          'config' => [
            'label' => $this->t('Cluster'),
          ],
        ],
      ];
    }
    $form['columns'] = [
      '#type' => 'configuration_container',
      '#title' => $this->t('Configured headline figures'),
      '#title_display' => 'invisble',
      '#default_value' => $default_value,
      '#allowed_item_types' => $this->getAllowedItemTypes(),
      '#preview' => [
        'columns' => [
          'label' => $this->t('Label'),
          // 'value' => $this->t('Value'),
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
   * @return \Drupal\node\NodeInterface[]
   *   An array of node objects.
   */
  private function loadNodesForEntities(array $entities) {
    $entity_ids = array_map(function ($entity) {
      return $entity->id;
    }, $entities);

    return NodeHelper::getNodesFromOriginalIds($entity_ids, 'governing_entity');
  }

  /**
   * Get the first entity node for column configuration.
   *
   * @return \Drupal\node\NodeInterface
   *   The first entity node available.
   */
  private function getFirstEntityNode() {
    $entities = $this->getEntityObjects();
    if (empty($entities)) {
      return NULL;
    }
    $entity = reset($entities);
    $entity_nodes = $this->loadNodesForEntities([$entity]);
    return !empty($entity_nodes) ? reset($entity_nodes) : NULL;
  }

  /**
   * Get the custom context for this block.
   *
   * @return array
   *   An array with context data or query handlers.
   */
  public function getBlockContext() {
    return [
      'page_node' => $this->getPageNode(),
      'plan_node' => $this->getCurrentPlanNode(),
      'context_node' => $this->getFirstEntityNode(),
    ];
  }

  /**
   * Get the allowed item types for this element.
   *
   * @return array
   *   An array with the allowed item types, keyed by the plugin id, with the
   *   value being an optional configuration array for the plugin.
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
      'attachment_data' => [
        'label' => $this->t('Caseload/indicator value'),
        'access' => [
          'node_type' => ['plan', 'governing_entity'],
        ],
        'data_point' => [
          'widget' => FALSE,
        ],
      ],
      'label_value' => [],
    ];
    return $item_types;
  }

}
