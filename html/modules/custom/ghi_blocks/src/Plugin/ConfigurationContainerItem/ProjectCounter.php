<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\FtsLinkTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides project based counter items for configuration containers.
 *
 * @todo This is still missing support for cluster filters.
 *
 * @ConfigurationContainerItem(
 *   id = "project_counter",
 *   label = @Translation("Project counter"),
 *   description = @Translation("This item displays project based counters."),
 * )
 */
class ProjectCounter extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;
  use FtsLinkTrait;

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
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planEntitiesQuery = $instance->endpointQueryManager->createInstance('plan_entities_query');
    $instance->projectSearchQuery = $instance->endpointQueryManager->createInstance('plan_project_search_query');
    $instance->flowSearchQuery = $instance->endpointQueryManager->createInstance('flow_search_query');
    $instance->clusterQuery = $instance->endpointQueryManager->createInstance('cluster_query');
    $instance->iconQuery = $instance->endpointQueryManager->createInstance('icon_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $context_node = $this->getContextValue('context_node');
    $plugin_configuration = $this->getPluginConfiguration();

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

    $cluster_restrict_disabled = array_key_exists('cluster_restrict', $plugin_configuration) && $plugin_configuration['cluster_restrict'] === FALSE;
    $cluster_restrict_bundles = ['plan', 'plan_entity'];
    if ($context_node && in_array($context_node->bundle(), $cluster_restrict_bundles) && !$cluster_restrict_disabled) {
      $element['cluster_restrict'] = $this->buildClusterRestrictFormElement($cluster_restrict);
    }

    // Add a preview.
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getValue($data_type, $cluster_restrict);
      $element['value_preview'] = $this->buildValuePreviewFormElement($preview_value);
    }

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
    $project_query = $this->initializeQuery();
    if (!$project_query) {
      return NULL;
    }
    $data_type = $data_type ?? $this->get('data_type');
    return $this->getValueForDataType($data_type, $project_query);
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
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();
    $classes[] = Html::getClass($this->getPluginId() . '--' . $this->get('data_type'));
    return $classes;
  }

  /**
   * Get the value for the given data type.
   *
   * @param string $data_type
   *   The data type.
   * @param \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery $project_query
   *   A project query instance, with cluster filters applied if appropriate.
   *
   * @return int
   *   The number of project related items of the given type.
   */
  private function getValueForDataType($data_type, PlanProjectSearchQuery $project_query) {
    $base_object = $this->getContextValue('base_object');
    switch ($data_type) {
      case 'projects_count':
        return $project_query->getProjectCount($base_object);

      case 'organizations_count':
        return $project_query->getOrganizationCount($base_object);
    }
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
    $data_type = $data_type ?? $this->get('data_type');
    $base_object = $this->getContextValue('base_object');
    $context_node = $this->getContextValue('context_node');
    switch ($data_type) {
      case 'projects_count':
        $route_name = 'ghi_plans.modal_content.projects';
        $width = '80%';
        break;

      case 'organizations_count':
        $route_name = 'ghi_plans.modal_content.organizations';
        $width = '50%';
        break;
    }

    $link_url = Url::fromRoute($route_name, [
      'base_object' => $base_object->id(),
    ]);
    $link_url->setOptions([
      'attributes' => [
        'class' => ['use-ajax', 'project-count-modal'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => Json::encode([
          'width' => $width,
          'title' => $this->t('@entity_label: @column_label', [
            '@entity_label' => $context_node->label(),
            '@column_label' => $this->getLabel(),
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
    $plan_object = $this->getContextValue('plan_object');
    if (!$plan_object) {
      return NULL;
    }
    $project_query->setPlaceholder('plan_id', $plan_object->get('field_original_id')->value);

    $context_node = $this->getContextValue('context_node');
    if (!$context_node) {
      return NULL;
    }

    // @todo Why is this needed?
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
