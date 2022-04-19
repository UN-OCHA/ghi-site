<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Template\Attribute;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\hpc_api\Query\EndpointQueryManager;

/**
 * Provides a cluster list item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "organization_cluster_list",
 *   label = @Translation("Clusters"),
 *   description = @Translation("This item displays a list of clusters per organization."),
 * )
 */
class OrganizationClusterList extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemValuePreviewTrait;

  const SORT_TYPE = 'alfa';
  const DATA_TYPE = 'string';
  const ITEM_TYPE = 'name';

  /**
   * The project search query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   */
  public $projectSearchQuery;

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

    $this->projectSearchQuery = $this->endpointQueryManager->createInstance('plan_project_search_query');
    $this->iconQuery = $this->endpointQueryManager->createInstance('icon_query');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['display_icons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display icons'),
      '#description' => $this->t('Check this if you want to display the cluster icons instead of the names.'),
      '#default_value'  => $this->get('display_icons') ?? FALSE,
    ];
    return $element;
  }

  /**
   * Get a default label.
   *
   * @return string
   *   A default label.
   */
  public function getDefaultLabel() {
    return $this->t('Clusters');
  }

  /**
   * Get the clusters for the current context.
   *
   * @return array
   *   An array of cluste objects.
   */
  private function getClusters() {
    $context_node = $this->getContextValue('context_node');
    $organization = $this->getContextValue('organization');
    $clusters_by_organizations = &drupal_static(__FUNCTION__, NULL);
    if ($clusters_by_organizations === NULL) {
      $clusters_by_organizations = $this->projectSearchQuery->getClustersByOrganization($context_node);
    }
    return $clusters_by_organizations[$organization->id] ?? NULL;
  }

  /**
   * Get the cluster names for the current context.
   *
   * @return string[]
   *   An array of cluster names.
   */
  public function getClusterNames() {
    $clusters = $this->getClusters();
    if (empty($clusters)) {
      return NULL;
    }
    return array_map(function ($cluster) {
      return $cluster->name;
    }, $clusters);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $cluster_names = $this->getClusterNames();
    if (empty($cluster_names)) {
      return NULL;
    }
    return implode(', ', $cluster_names);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $clusters = $this->getClusters();
    if (empty($clusters)) {
      return NULL;
    }
    $display_icons = $this->get('display_icons') ?? FALSE;
    $attributes = new Attribute();
    $attributes->addClass(count($clusters) > 1 ? 'multiple' : 'single');
    $attributes->addClass($display_icons ? 'display-icons' : 'display-text');

    if (!$display_icons) {
      return [
        '#markup' => Markup::create(implode(' | ', $this->getClusterNames())),
        '#attributes' => $attributes,
      ];
    }

    $content = [];
    if ($display_icons) {
      $content = array_merge($content, array_map(function ($cluster) {
        return [
          '#markup' => Markup::create($this->iconQuery->getIconEmbedCode($cluster->value->icon)),
        ];
      }, $clusters));
    }
    return [
      '#type' => 'container',
      '#attributes' => $attributes,
    ] + $content;
  }

}
