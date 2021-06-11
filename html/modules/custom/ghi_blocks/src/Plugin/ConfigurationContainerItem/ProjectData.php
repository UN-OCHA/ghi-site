<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\ClusterRestrictConfigurationItemTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Query\ClusterQuery;
use Drupal\ghi_plans\Query\FlowSearchQuery;
use Drupal\ghi_plans\Query\PlanEntitiesQuery;
use Drupal\ghi_plans\Query\PlanProjectSearchQuery;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Drupal\node\NodeInterface;

/**
 * Provides an entity counter item for configuration containers.
 *
 * @todo This is still missing support for cluster filters.
 *
 * @ConfigurationContainerItem(
 *   id = "project_data",
 *   label = @Translation("Project data"),
 * )
 */
class ProjectData extends ConfigurationContainerItemPluginBase {

  use ClusterRestrictConfigurationItemTrait;

  /**
   * The plan entities query.
   *
   * @var \Drupal\ghi_plans\Query\PlanEntitiesQuery
   */
  public $planEntitiesQuery;

  /**
   * The project search query.
   *
   * @var \Drupal\ghi_plans\Query\PlanProjectSearchQuery
   */
  public $projectSearchQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\FlowSearchQuery
   */
  public $flowSearchQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\ClusterQuery
   */
  public $clusterQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PlanEntitiesQuery $plan_entities_query, PlanProjectSearchQuery $project_search_query, FlowSearchQuery $flow_search_query, ClusterQuery $cluster_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->planEntitiesQuery = $plan_entities_query;
    $this->projectSearchQuery = $project_search_query;
    $this->flowSearchQuery = $flow_search_query;
    $this->clusterQuery = $cluster_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.plan_entities_query'),
      $container->get('ghi_plans.plan_project_search_query'),
      $container->get('ghi_plans.flow_search_query'),
      $container->get('ghi_plans.cluster_query'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#description'] = $this->t('Leave empty to use a default label');

    $context = $this->getContext();

    $data_type_options = [
      'projects_count' => $this->t('Projects count'),
      'organizations_count' => $this->t('Partners count'),
    ];
    $data_type = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);
    $cluster_restrict = $this->getSubmittedValue($element, $form_state, 'cluster_restrict', [
      'type' => NULL,
      'tag' => NULL,
    ]);

    $element['data_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $data_type_options,
      '#default_value' => $data_type,
      '#weight' => 0,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
    $element['label']['#weight'] = 1;
    $element['label']['#placeholder'] = $this->getDefaultLabel($data_type);

    if (in_array($context['page_node']->bundle(), ['plan', 'plan_entity'])) {
      $element['cluster_restrict'] = $this->buildClusterRestrictFormElement($cluster_restrict);
    }

    // Add a preview.
    $element['value_preview'] = [
      '#type' => 'item',
      '#title' => $this->t('Value preview'),
      '#markup' => $this->getValue($data_type, $cluster_restrict),
      '#weight' => 3,
    ];

    return $element;
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
  public function getDefaultLabel($data_type = NULL) {
    $data_type = $data_type ?: $this->get('data_type');
    $default_map = [
      'projects_count' => $this->t('Projects'),
      'organizations_count' => $this->t('Partners'),
    ];
    return $data_type ? $default_map[$data_type] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($data_type = NULL, $cluster_restrict = NULL) {
    $context = $this->getContext();
    if (empty($context['page_node'])) {
      return NULL;
    }

    $project_query = $this->projectSearchQuery;
    $page_node = $context['page_node'];

    $data_type = $data_type ?? $this->get('data_type');
    $cluster_restrict = $cluster_restrict ?? $this->get('cluster_restrict');
    if (!empty($cluster_restrict) && $cluster_ids = $this->getClusterIdsForConfig($cluster_restrict)) {
      $project_query->setFilterByClusterIds($cluster_ids);
    }

    switch ($data_type) {
      case 'projects_count':
        return $project_query->getProjectCount($page_node);

      case 'organizations_count':
        return $project_query->getOrganizationCount($page_node);
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setContext($context) {
    parent::setContext($context);

    // Also set cluster context if the current page is a plan entity.
    $page_node = $context['page_node'] ?? NULL;
    if ($page_node && $page_node->bundle() == 'plan_entity') {
      $query_handlers = $this->getQueryHandlers();
      foreach ($query_handlers as $query) {
        if (!$query instanceof PlanProjectSearchQuery) {
          continue;
        }
        $cluster_ids = PlanStructureHelper::getPlanEntityStructure($this->planEntitiesQuery->getData());
        $query->setFilterByClusterIds($cluster_ids);
      }
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
    $plan_node = $context['plan_node'];
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
    if (empty($context['plan_node']) || $context['plan_node']->bundle() != 'plan') {
      return FALSE;
    }
    if (!empty($access_requirements['plan_costing'])) {
      $allowed = $allowed && $this->accessByPlanCosting($context['plan_node'], $access_requirements['plan_costing']);
    }
    return $allowed;
  }

  /**
   * Check access by plan costing type.
   *
   * @param \Drupal\node\NodeInterface $plan_node
   *   A plan node object.
   * @param array $valid_type_codes
   *   An array with the valid type codes.
   *
   * @return bool
   *   The access status.
   */
  public function accessByPlanCosting(NodeInterface $plan_node, array $valid_type_codes) {
    if ($plan_node->field_plan_costing->isEmpty()) {
      // If no plan costing is set for this plan, we only need to check if
      // costing code "0" is valid.
      return in_array(0, $valid_type_codes);
    }
    // Otherwhise we load the plan costing term, get the code and check if it's
    // one of the valid ones.
    $term = TaxonomyHelper::getTermById($plan_node->field_plan_costing->target_id, 'plan_costing');
    return $term ? in_array($term->field_plan_costing_code->value, $valid_type_codes) : FALSE;
  }

}
