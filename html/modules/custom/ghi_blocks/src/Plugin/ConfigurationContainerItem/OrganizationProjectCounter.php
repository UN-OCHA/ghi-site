<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an organization projects counter item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'organization_project_counter',
  label: new TranslatableMarkup('Project counter'),
  description: new TranslatableMarkup('This item displays a project counter per organization.'),
)]
class OrganizationProjectCounter extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemValuePreviewTrait;

  /**
   * The project search query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery
   */
  public $projectQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): OrganizationProjectCounter {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->projectQuery = $instance->fabricQueryManager->createInstance('project');
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
    $plan_object = $this->getContextValue('plan_object');
    $base_object = $this->getContextValue('base_object');
    $organization = $this->getContextValue('organization');
    $projects = $this->getContextValue('projects');
    if (is_array($projects)) {
      $projects_by_organization = $this->getProjectsByOrganization($projects);
      return $projects_by_organization[$organization->id()] ?? [];
    }
    return $this->projectQuery->getProjectsForPlanId($plan_object->getSourceId(), $base_object instanceof BaseObjectChildInterface ? $base_object : NULL, $organization->id());
  }

  /**
   * Group the given project list by organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to group.
   *
   * @return array[]
   *   The projects keyed by organization id and project id.
   */
  private function getProjectsByOrganization(array $projects): array {
    $projects_by_organization = &drupal_static(static::class . '::' . __FUNCTION__, []);
    $cache_key = $this->getProjectsCacheKey();
    if (!array_key_exists($cache_key, $projects_by_organization)) {
      $projects_by_organization[$cache_key] = [];
      foreach ($projects as $project) {
        foreach ($project->getOrganizations() as $organization) {
          $projects_by_organization[$cache_key][$organization->id()][$project->id()] = $project;
        }
      }
    }
    return $projects_by_organization[$cache_key];
  }

  /**
   * Get a static cache key for the current plan and optional cluster context.
   *
   * @return string
   *   The cache key.
   */
  private function getProjectsCacheKey(): string {
    $plan_object = $this->getContextValue('plan_object');
    $base_object = $this->getContextValue('base_object');
    $parts = [
      $plan_object instanceof Plan ? $plan_object->id() : NULL,
      $plan_object instanceof Plan ? $plan_object->getSourceId() : NULL,
      $base_object instanceof BaseObjectChildInterface ? $base_object->id() : NULL,
      $base_object instanceof BaseObjectChildInterface ? $base_object->getSourceId() : NULL,
    ];
    return implode(':', array_map(fn ($part) => $part ?? 'none', $parts));
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
    $plan_object = $this->getContextValue('plan_object');
    $t_options = [
      'langcode' => $plan_object instanceof Plan ? $plan_object->getPlanLanguage() : NULL,
    ];

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
          ], $t_options),
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
    ], $t_options);
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
