<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an organization projects counter item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "organization_project_counter",
 *   label = @Translation("Project counter"),
 *   description = @Translation("This item displays a project counter per organization."),
 * )
 */
class OrganizationProjectCounter extends ConfigurationContainerItemPluginBase {

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
    /** @var \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\OrganizationProjectCounter $instance */
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
    $base_object = $this->getContextValue('base_object');
    $organization = $this->getContextValue('organization');
    return $this->projectSearchQuery->getOrganizationProjects($organization, $base_object);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return count($this->getProjects());
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $modal_link = $this->getModalLink();
    if (!$modal_link || empty($this->getValue())) {
      return parent::getRenderArray();
    }
    return [
      '#type' => 'container',
      0 => parent::getRenderArray(),
      'tooltips' => [
        '#theme' => 'hpc_tooltip_wrapper',
        '#tooltips' => [$modal_link],
      ],
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
    $base_object = $this->getContextValue('base_object');
    $organization = $this->getContextValue('organization');

    $route_name = 'ghi_plans.modal_content.organization_projects';
    $width = '80%';

    $link_url = Url::fromRoute($route_name, [
      'organization_id' => $organization->id(),
      'base_object' => $base_object->id(),
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
      '#icon' => 'view_list',
      '#tag' => 'span',
    ];
    $link = Link::fromTextAndUrl($text, $link_url);
    $tooltip = $this->t('Click to see detailed data for <em>@column_label</em>.', [
      '@column_label' => $this->getLabel(),
    ]);
    $modal_link = [
      '#theme' => 'hpc_modal_link',
      '#link' => $link->toRenderable(),
      '#tooltip' => $tooltip,
      '#attributes' => [
        'aria-label' => $tooltip,
      ],
    ];
    return $modal_link;
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
