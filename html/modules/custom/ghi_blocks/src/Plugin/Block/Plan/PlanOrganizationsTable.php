<?php

namespace Drupal\ghi_blocks\Plugin\Block\Plan;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\GHIBlockBase;
use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\ProjectFunding;
use Drupal\ghi_blocks\Traits\OrganizationsBlockTrait;
use Drupal\ghi_blocks\Traits\TableSoftLimitTrait;
use Drupal\ghi_form_elements\Traits\ConfigurationContainerTrait;
use Drupal\hpc_downloads\Interfaces\HPCDownloadExcelInterface;
use Drupal\hpc_downloads\Interfaces\HPCDownloadPNGInterface;

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
 *    "plan" = @ContextDefinition("entity:base_object", label = @Translation("Plan"), constraints = { "Bundle": "plan" }),
 *    "plan_cluster" = @ContextDefinition("entity:base_object", label = @Translation("Cluster"), constraints = { "Bundle": "governing_entity" }, required =  FALSE)
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
class PlanOrganizationsTable extends GHIBlockBase implements ConfigurableTableBlockInterface, MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface, HPCDownloadExcelInterface, HPCDownloadPNGInterface {

  use ConfigurationContainerTrait;
  use TableSoftLimitTrait;
  use OrganizationsBlockTrait;

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
      '#progress_groups' => TRUE,
      '#soft_limit' => $this->getBlockConfig()['display']['soft_limit'] ?? 0,
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

        $progress_group = NULL;
        if ($item_type instanceof ProjectFunding) {
          $progress_group = $item_type->get('data_type') == 'coverage' ? 'percentage' : $item_type->get('data_type');
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
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * Form callback for the organizations form.
   */
  public function organizationsForm(array $form, FormStateInterface $form_state) {
    $organization_options = $this->getAvailableOrganizationOptions();

    $header_text = $this->t('Found @count organizations with projects. Select the ones that should be visible below. If no organization is selected, all organizations will be shown.', [
      '@count' => count($organization_options),
    ]);
    $form['organization_ids_header'] = [
      '#type' => 'markup',
      '#markup' => $header_text,
      '#prefix' => '<div>',
      '#suffix' => '</div><br />',
    ];

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

    $form['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
        ],
      ],
    ];
    $form['actions']['select_organizations'] = [
      '#type' => 'submit',
      '#value' => $this->t('Use selected organizations'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'table',
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
    if ($cluster_context = $this->getClusterContext()) {
      $project_search_query->setClusterContext($cluster_context->getSourceId());
    }
    return [
      'page_node' => $this->getPageNode(),
      'plan_object' => $this->getCurrentPlanObject(),
      'base_object' => $this->getCurrentBaseObject(),
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
