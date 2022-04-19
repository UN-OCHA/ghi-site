<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\TaxonomyHelper;

/**
 * Provides project based counter items for configuration containers.
 *
 * @todo This is still missing support for cluster filters.
 *
 * @ConfigurationContainerItem(
 *   id = "organization_project_counter",
 *   label = @Translation("Project counter"),
 *   description = @Translation("This item displays a project counter per organization."),
 * )
 */
class OrganizationProjectCounter extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;

  /**
   * The plan entities query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   */
  public $planEntitiesQuery;

  /**
   * The project search query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   */
  public $projectSearchQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   */
  public $flowSearchQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\ClusterQuery
   */
  public $clusterQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);

    $this->planEntitiesQuery = $this->endpointQueryManager->createInstance('plan_entities_query');
    $this->projectSearchQuery = $this->endpointQueryManager->createInstance('plan_project_search_query');
    $this->flowSearchQuery = $this->endpointQueryManager->createInstance('flow_search_query');
    $this->clusterQuery = $this->endpointQueryManager->createInstance('cluster_query');
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    $label = parent::getLabel();
    return $label ?: $this->getDefaultLabel();
  }

  /**
   * Get a default label.
   *
   * @return string|null
   *   A default label or NULL.
   */
  public function getDefaultLabel() {
    return $this->t('Projects');
  }

  /**
   * Get the projects for the current context.
   *
   * @return array
   *   An array of project objects.
   */
  private function getProjects() {
    $project_query = $this->initializeQuery();
    if (!$project_query) {
      return NULL;
    }
    $context_node = $this->getContextValue('context_node');
    $organization = $this->getContextValue('organization');

    return $project_query->getOrganizationProjects($organization, $context_node);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($cluster_restrict = NULL) {
    return count($this->getProjects());
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $popover = $this->getPopover();
    if (!$popover) {
      return parent::getRenderArray();
    }
    return [
      '#type' => 'container',
      0 => parent::getRenderArray(),
      1 => $popover,
    ];
  }

  /**
   * Get a popover for the current value.
   *
   * Those are either projects or organizations.
   *
   * @return array|null
   *   An render array for the popover.
   */
  private function getPopover() {
    $organization = $this->getContextValue('organization');
    $popover_content = $this->getProjectPopoverContent($this->getProjects());
    return [
      '#theme' => 'hpc_popover',
      '#title' => Markup::create('<span class="name">' . $organization->name . '</span>'),
      '#content' => [
        $popover_content,
      ],
      '#class' => 'project-data project-data-popover',
      '#material_icon' => 'table_view',
      '#disabled' => empty($popover_content),
    ];
  }

  /**
   * Get the popover content for project items.
   *
   * @param array $projects
   *   The projects to include in the table.
   *
   * @return array
   *   A render array.
   */
  private function getProjectPopoverContent(array $projects) {
    $header = [
      $this->t('Project code'),
      $this->t('Project name'),
      $this->t('Requirements'),
    ];

    $rows = [];
    foreach ($projects as $project) {
      $row = [];
      $row[] = [
        'data' => [
          '#type' => 'link',
          '#title' => $project->version_code,
          '#url' => Url::fromUri('https://projects.hpc.tools/project/' . $project->id . '/view'),
        ],
      ];
      $row[] = $project->name;
      $row[] = [
        'data' => [
          '#theme' => 'hpc_currency',
          '#value' => $project->requirements,
        ],
      ];
      $rows[] = $row;
    }

    return [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
    ];
  }

  /**
   * Initialize the project query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   *   A project query instance, with cluster filters applied if appropriate.
   */
  private function initializeQuery() {
    $project_query = $this->projectSearchQuery;
    $cluster_restrict = $cluster_restrict ?? $this->get('cluster_restrict');
    if (!empty($cluster_restrict) && $cluster_ids = $this->getClusterIdsForConfig($cluster_restrict)) {
      $project_query->setFilterByClusterIds($cluster_ids);
    }
    return $project_query;
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($context) {
    parent::setContext($context);

    // Also set cluster context if the current page is a plan entity.
    $context_node = $context['context_node'] ?? NULL;
    if ($context_node && $context_node->bundle() == 'plan_entity' && $this->projectSearchQuery) {
      $cluster_ids = PlanStructureHelper::getPlanEntityStructure($this->planEntitiesQuery->getData());
      $this->projectSearchQuery->setFilterByClusterIds($cluster_ids);
    }
  }

  /**
   * Get the cluster ids for the current item configuration.
   *
   * @param array $cluster_restrict
   *   The cluster restrict configuration.
   *
   * @return int[]
   *   An array of cluster ids.
   */
  private function getClusterIdsForConfig(array $cluster_restrict) {
    $context = $this->getContext();
    $plan_node = $context['plan_object'];
    $plan_id = $plan_node->field_original_id->value;

    // Extract the actually used cluster from the funding and requirements data.
    $search_results = $this->flowSearchQuery->search([
      'planid' => $plan_id,
      'groupby' => 'cluster',
    ]);

    return $this->getClusterIdsByClusterRestrict($cluster_restrict, $search_results, $this->clusterQuery);
  }

  /**
   * Access callback.
   *
   * @param array $context
   *   A context array.
   * @param array $access_requirements
   *   An array with access requirements.
   *
   * @return bool
   *   The access status.
   */
  public function access(array $context, array $access_requirements) {
    $allowed = TRUE;
    if (empty($context['plan_object']) || $context['plan_object']->bundle() != 'plan') {
      return FALSE;
    }
    if (!empty($access_requirements['plan_costing'])) {
      $allowed = $allowed && $this->accessByPlanCosting($context['plan_object'], $access_requirements['plan_costing']);
    }
    return $allowed;
  }

  /**
   * Check access by plan costing type.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $plan_object
   *   A plan node object.
   * @param array $valid_type_codes
   *   An array with the valid type codes.
   *
   * @return bool
   *   The access status.
   */
  public function accessByPlanCosting(ContentEntityInterface $plan_object, array $valid_type_codes) {
    if ($plan_object->field_plan_costing->isEmpty()) {
      // If no plan costing is set for this plan, we only need to check if
      // costing code "0" is valid.
      return in_array(0, $valid_type_codes);
    }
    // Otherwhise we load the plan costing term, get the code and check if it's
    // one of the valid ones.
    $term = TaxonomyHelper::getTermById($plan_object->field_plan_costing->target_id, 'plan_costing');
    return $term ? in_array($term->field_plan_costing_code->value, $valid_type_codes) : FALSE;
  }

}
