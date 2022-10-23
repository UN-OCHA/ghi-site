<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planEntitiesQuery = $instance->endpointQueryManager->createInstance('plan_entities_query');
    $instance->projectSearchQuery = $instance->endpointQueryManager->createInstance('plan_project_search_query');
    $instance->flowSearchQuery = $instance->endpointQueryManager->createInstance('flow_search_query');
    $instance->clusterQuery = $instance->endpointQueryManager->createInstance('cluster_query');
    return $instance;
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
    $plan_object = $this->getContextValue('plan_object');
    $organization = $this->getContextValue('organization');
    return $project_query->getOrganizationProjects($organization, $plan_object);
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
    $modal_link = $this->getModalLink();
    if (!$modal_link) {
      return parent::getRenderArray();
    }
    return [
      '#type' => 'container',
      0 => parent::getRenderArray(),
      1 => $modal_link,
    ];
  }

  /**
   * Get a modal link for the current value.
   *
   * Those are either projects or organizations modals.
   *
   * @return array|null
   *   An render array for the modal link.
   */
  private function getModalLink() {
    $plan_object = $this->getContextValue('plan_object');
    $organization = $this->getContextValue('organization');

    $route_name = 'ghi_plans.modal_content.organization_projects';
    $width = '80%';

    $link_url = Url::fromRoute($route_name, [
      'organization_id' => $organization->id(),
      'base_object' => $plan_object->id(),
    ]);
    $link_url->setOptions([
      'attributes' => [
        'class' => ['use-ajax', 'project-count-modal'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => $width,
          'title' => $this->t('@organization: Projects', [
            '@organization' => $organization->getName(),
          ]),
          'classes' => [
            'ui-dialog' => 'project-count-modal ghi-modal-dialog',
          ],
        ]),
        'rel' => 'nofollow',
      ],
    ]);

    $text = [
      '#theme' => 'hpc_icon',
      '#icon' => 'table_view',
      '#tag' => 'span',
    ];
    $link = Link::fromTextAndUrl($text, $link_url);
    $modal_link = [
      '#theme' => 'hpc_modal_link',
      '#link' => $link->toRenderable(),
      '#tooltip' => $this->t('Click to see detailed data for <em>@column_label</em>.', [
        '@column_label' => $this->getLabel(),
      ]),
    ];
    return $modal_link;
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
    $base_object = $context['base_object'] ?? NULL;
    if ($base_object && $base_object->bundle() == 'plan_entity' && $this->projectSearchQuery) {
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
