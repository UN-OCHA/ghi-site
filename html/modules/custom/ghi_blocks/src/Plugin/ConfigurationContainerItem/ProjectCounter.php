<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Traits\FtsLinkTrait;
use Drupal\hpc_common\Helpers\TaxonomyHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides project based counter items for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'project_counter',
  label: new TranslatableMarkup('Project counter'),
  description: new TranslatableMarkup('This item displays project based counters.'),
)]
class ProjectCounter extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;
  use FtsLinkTrait;

  /**
   * The project query.
   *
   * @var \Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery
   */
  public $projectQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\FlowSearchQuery
   */
  public $flowSearchQuery;

  /**
   * The icon query.
   *
   * @var \Drupal\hpc_api\Plugin\EndpointQuery\IconQuery
   */
  public $iconQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): ProjectCounter {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->projectQuery = $instance->fabricQueryManager->createInstance('project');
    $instance->flowSearchQuery = $instance->endpointQueryManager->createInstance('flow_search_query');
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
    $data_type = $data_type ?? $this->get('data_type');
    return $this->getValueForDataType($data_type);
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
   *
   * @return int
   *   The number of project related items of the given type.
   */
  private function getValueForDataType($data_type) {
    $plan_object = $this->getContextValue('plan_object');
    $base_object = $this->getContextValue('base_object');
    $base_object = $base_object instanceof BaseObjectChildInterface ? $base_object : NULL;
    switch ($data_type) {
      case 'projects_count':
        return count($this->projectQuery->getProjectsForPlanId($plan_object->getSourceId(), $base_object));

      case 'organizations_count':
        return count($this->projectQuery->getProjectOrganizationsForPlan($plan_object, $base_object));
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
    /** @var \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object */
    $base_object = $this->getContextValue('base_object');
    $context_node = $this->getContextValue('context_node');

    $route_name = NULL;
    $dialog_classes = ['project-count-modal', 'ghi-modal-dialog'];
    switch ($data_type) {
      case 'projects_count':
        $route_name = 'ghi_plans.modal_content.projects';
        $width = '80%';
        $dialog_classes[] = 'project-count-modal--projects';
        break;

      case 'organizations_count':
        $route_name = 'ghi_plans.modal_content.organizations';
        $width = '40%';
        break;
    }

    if (!$route_name) {
      return NULL;
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
            '@entity_label' => $context_node ? $context_node->label() : $base_object->getName(),
            '@column_label' => $this->getLabel(),
          ]),
          'classes' => [
            'ui-dialog' => implode(' ', $dialog_classes),
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
