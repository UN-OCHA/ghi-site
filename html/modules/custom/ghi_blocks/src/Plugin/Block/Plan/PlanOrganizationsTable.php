<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Traits\OrganizationsBlockTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\ghi_element_sync\SyncableBlockInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a 'PlanOrganizationsTable' block.
 *
 * @Block(
 *  id = "plan_organizations_table",
 *  admin_label = @Translation("Organizations Table"),
 *  category = @Translation("Plan elements"),
 *  data_sources = {
 *    "entities" = "plan_entities_query",
 *    "project_search" = "plan_project_search_query",
 *    "project_funding" = "plan_project_funding_query",
 *  },
 *  default_title = @Translation("Organizations overview"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" })
 *  },
 *  config_forms = {
 *    "organizations" = {
 *      "title" = @Translation("Organizations"),
 *      "callback" = "organizationsForm",
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
class PlanOrganizationsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, SyncableBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use ConfigurationContainerTrait;
  use TableSoftLimitTrait;
  use OrganizationsBlockTrait;

  /**
   * {@inheritdoc}
   */
  public static function mapConfig($config, NodeInterface $node, $element_type, $dry_run = FALSE) {
    $columns = [];
    // Define a transition map.
    $transition_map = [
      'organization_name' => [
        'target' => 'entity_name',
      ],
      'project_codes' => [
        'target' => 'organization_project_counter',
      ],
      'clusters' => [
        'target' => 'organization_cluster_list',
        'config' => ['display_icons' => FALSE],
      ],
      // 'plan_entities' => [],
      'original_requirements' => [
        'target' => 'project_funding',
        'config' => ['data_type' => 'original_requirements'],
      ],
      'current_requirements' => [
        'target' => 'project_funding',
        'config' => ['data_type' => 'current_requirements'],
      ],
      'total_funding' => [
        'target' => 'project_funding',
        'config' => ['data_type' => 'total_funding'],
      ],
      'coverage' => [
        'target' => 'project_funding',
        'config' => ['data_type' => 'coverage'],
      ],
      'requirements_changes' => [
        'target' => 'project_funding',
        'config' => ['data_type' => 'requirements_changes'],
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
      if (is_object($value) && property_exists($value, 'display_icons')) {
        $item['config']['display_icons'] = $value->display_icons;
      }
      if (is_object($value) && property_exists($value, 'cluster_restrict') && property_exists($value, 'cluster_tag')) {
        $item['config']['cluster_restrict'] = [
          'type' => $value->cluster_restrict,
          'tag' => $value->cluster_tag,
        ];
      }
      $columns[] = $item;
    }
    return [
      'label' => property_exists($config, 'widget_title') ? $config->widget_title : NULL,
      'label_display' => TRUE,
      'hpc' => [
        'organizations' => [
          'organization_ids' => (array) $config->organization_ids ?? [],
        ],
        'table' => [
          'columns' => $columns,
        ],
        'display' => [
          'soft_limit' => 5,
        ],
      ],
    ];
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

    $organizations = $this->getConfiguredOrganizations();
    $columns = $this->getConfiguredItems($conf['table']['columns']);

    if (empty($columns) || empty($organizations)) {
      return NULL;
    }

    $context = $this->getBlockContext();

    $rows = [];
    foreach ($organizations as $organization) {

      $context['organization'] = $organization;
      $context['entity'] = $organization;

      $row = [];
      $skip_row = FALSE;
      foreach ($columns as $column) {

        /** @var \Drupal\ghi_form_elements\ConfigurationContainerItemPluginInterface $item_type */
        $item_type = $this->getItemTypePluginForColumn($column, $context);

        // Then add the value to the row.
        $row[] = [
          'data' => $item_type->getRenderArray(),
          'data-value' => $item_type->getValue(),
          'data-raw-value' => $item_type->getSortableValue(),
          'data-sort-type' => $item_type::SORT_TYPE,
          'data-column-type' => $item_type->getColumnType(),
          'class' => $item_type->getClasses(),
          'export_value' => $item_type->getSortableValue(),
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

    return [
      'header' => $this->buildTableHeader($columns),
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
      'organizations' => [
        // It is important to set the default value to NULL, otherwhise the
        // array keys, which are integers will be renumbered by
        // NestedArray::mergeDeep() which is called from
        // BlockPluginTrait::setConfiguration().
        'organization_ids' => NULL,
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
    return 'organizations';
  }

  /**
   * Form callback for the organizations form.
   */
  public function organizationsForm(array $form, FormStateInterface $form_state) {
    $organization_options = $this->getAvailableOrganizationOptions();
    $form['organization_ids'] = [
      '#type' => 'tableselect',
      '#header' => [
        'id' => $this->t('ID'),
        'organization_name' => $this->t('Organization'),
      ],
      '#options' => $organization_options,
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'organization_ids') ?: [],
      '#empty' => $this->t('No organizations found.'),
    ];
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
            'label' => $this->t('Organization'),
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
   * Get the organization options available in the current context.
   *
   * @return array
   *   An array of organizations, keyed by id.
   */
  private function getAvailableOrganizationOptions() {
    $organizations = $this->getOrganizations();
    return array_map(function ($organization) {
      return [
        'id' => $organization->id,
        'organization_name' => $organization->name,
      ];
    }, $organizations);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedItemTypes() {
    $item_types = [
      'entity_name' => [],
      'organization_project_counter' => [],
      'organization_cluster_list' => [],
      // 'plan_entities' => [],
      'project_funding' => [
        'value_preview' => FALSE,
      ],
    ];
    return $item_types;
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContext() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery $project_search_query */
    $project_search_query = $this->getQueryHandler('project_search');
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'projects' => $project_search_query->getProjects(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildDownloadData() {
    return $this->buildTableData();
  }

}
