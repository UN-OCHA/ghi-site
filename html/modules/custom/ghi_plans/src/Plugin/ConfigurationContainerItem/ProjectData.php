<?php

namespace Drupal\ghi_plans\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_configuration_container\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\PlanStructureHelper;
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

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['label']['#description'] = $this->t('Leave empty to use a default label');

    $data_type_options = [
      'projects_count' => $this->t('Projects count'),
      'organizations_count' => $this->t('Partners count'),
    ];
    $data_type = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);

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
  public function getValue() {
    $context = $this->getContext();
    if (empty($context['page_node']) || empty($context['project_search_query'])) {
      return NULL;
    }
    $query = $context['project_search_query'];
    $page_node = $context['page_node'];
    if ($page_node->bundle() == 'plan_entity' && !empty($context['entity_query'])) {
      $cluster_ids = PlanStructureHelper::getPlanEntityStructure($context['entity_query']->getData());
      $query->setFilterByClusterIds($cluster_ids);
    }

    switch ($this->get('data_type')) {
      case 'projects_count':
        return $query->getProjectCount($page_node);

      case 'organizations_count':
        return $query->getOrganizationCount($page_node);
    }

    return NULL;
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
    $term = TaxonomyHelper::getTermById($plan_node->field_plan_costing->target_id, 'plan_costing');
    return $term ? in_array($term->field_plan_costing_code->value, $valid_type_codes) : FALSE;
  }

}
