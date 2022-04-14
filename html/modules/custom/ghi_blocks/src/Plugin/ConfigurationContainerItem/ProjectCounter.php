<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\FtsLinkTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;

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
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);

    $this->planEntitiesQuery = $this->endpointQueryManager->createInstance('plan_entities_query');
    $this->projectSearchQuery = $this->endpointQueryManager->createInstance('plan_project_search_query');
    $this->flowSearchQuery = $this->endpointQueryManager->createInstance('flow_search_query');
    $this->clusterQuery = $this->endpointQueryManager->createInstance('cluster_query');
    $this->iconQuery = $this->endpointQueryManager->createInstance('icon_query');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $context = $this->getContext();
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
    if (in_array($context['context_node']->bundle(), ['plan', 'plan_entity']) && !$cluster_restrict_disabled) {
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
    $data_type = $data_type ?? $this->get('data_type');
    $cluster_restrict = $cluster_restrict ?? $this->get('cluster_restrict');

    $project_query = $this->initializeQuery($data_type, $cluster_restrict);
    if (!$project_query) {
      return NULL;
    }
    return $this->getValueForDataType($data_type, $project_query);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $project_query = $this->initializeQuery();
    if (!$project_query) {
      return NULL;
    }

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
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();
    $classes[] = Html::getClass($this->getPluginId() . '--' . $this->get('data_type'));
    if (empty($this->getValue())) {
      $classes[] = 'empty';
    }
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
    $context_node = $this->getContextValue('context_node');
    switch ($data_type) {
      case 'projects_count':
        return $project_query->getProjectCount($context_node);

      case 'organizations_count':
        return $project_query->getOrganizationCount($context_node);
    }
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
    $project_query = $this->initializeQuery();
    $data_type = $data_type ?? $this->get('data_type');
    $context_node = $this->getContextValue('context_node');

    $fts_link = NULL;
    $link_title = $this->t('For more details, view on <img src="@logo_url" />', [
      '@logo_url' => ThemeHelper::getUriToFtsIcon(),
    ]);
    $needs_fts_link = $context_node->bundle() == 'governing_entity';

    $popover_content = NULL;
    switch ($data_type) {
      case 'projects_count':
        $objects = $project_query->getProjects($context_node);
        $popover_content = $this->getProjectPopoverContent($objects);
        $fts_link = $needs_fts_link ? self::buildFtsLink($link_title, $this->getContextValue('plan_object'), 'projects', $context_node) : NULL;
        break;

      case 'organizations_count':
        $objects = $project_query->getOrganizations($context_node);
        $popover_content = $this->getOrganizationPopoverContent($objects);
        $fts_link = $needs_fts_link ? self::buildFtsLink($link_title, $this->getContextValue('plan_object'), 'recipients', $context_node) : NULL;
        break;
    }

    $entity = $this->getContextValue('entity');
    // Get the icon if there is any.
    $icon = NULL;
    if ($entity && !empty($entity->icon)) {
      $icon = $this->iconQuery->getIconEmbedCode($entity->icon);
    }

    return [
      '#theme' => 'hpc_popover',
      '#title' => Markup::create($icon . '<span class="name">' . $this->getLabel() . '</span>'),
      '#content' => [
        $fts_link,
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
      $this->t('Organizations'),
      $this->t('Project Target'),
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
          '#theme' => 'item_list',
          '#items' => $this->getOrganizationLinks($project->organizations),
        ],
      ];
      $row[] = [
        'data' => [
          '#theme' => 'hpc_amount',
          '#amount' => $project->target,
          '#scale' => 'full',
        ],
      ];
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
   * Get the popover content for oragnization items.
   *
   * @param array $organizations
   *   The organizations to include in the table.
   *
   * @return array
   *   A table render array.
   */
  private function getOrganizationPopoverContent(array $organizations) {
    $links = $this->getOrganizationLinks($organizations);
    $popover_content = [
      '#theme' => 'item_list',
      '#items' => $links,
      '#list_type' => 'ol',
    ];
    return $popover_content;
  }

  /**
   * Get organization links when available.
   *
   * @param array $objects
   *   The organization objects.
   *
   * @return array
   *   An array of organization links, or their names if no url is set.
   */
  private function getOrganizationLinks(array $objects) {
    return array_values(array_map(function ($object) {
      return $object->url ? Link::fromTextAndUrl($object->name, Url::fromUri($object->url)) : $object->name;
    }, $objects));
  }

  /**
   * Initialize the project query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   *   A project query instance, with cluster filters applied if appropriate.
   */
  private function initializeQuery($data_type = NULL, $cluster_restrict = NULL) {
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

    $data_type = $data_type ?? $this->get('data_type');
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
