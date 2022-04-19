<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides project funding items for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "project_funding",
 *   label = @Translation("Project funding"),
 *   description = @Translation("This item displays project funding information."),
 * )
 */
class ProjectFunding extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;

  /**
   * The project search query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectFundingQuery
   */
  public $projectFundingQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);

    $this->projectFundingQuery = $this->endpointQueryManager->createInstance('plan_project_funding_query');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $data_type_options = $this->getDataTypes();
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

    // Add a preview.
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getValue($data_type);
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
    $default_map = $this->getDataTypes();
    return $data_type ? $default_map[$data_type] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($data_type = NULL) {
    $data_type = $data_type ?? $this->get('data_type');
    $organization = $this->getContextValue('organization');
    $projects = $this->getContextValue('projects');
    $value = NULL;
    switch ($data_type) {
      case 'original_requirements':
      case 'current_requirements':
      case 'total_funding':
        $value = $this->projectFundingQuery->getSumForOrganization($data_type, $organization, $projects);
        break;

      case 'coverage':
        $value = $this->projectFundingQuery->getFundingCoverageForOrganization($organization, $projects);
        break;

      case 'requirements_changes':
        $value = $this->projectFundingQuery->getRequirementsChangesForOrganization($organization, $projects);
        break;
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $data_type = $data_type ?? $this->get('data_type');
    $additional_theme_options = [
      'formatting_decimals' => $this->getContextValue('plan_object')->field_decimal_format->value,
    ];
    $build = [];
    switch ($data_type) {
      case 'original_requirements':
      case 'current_requirements':
      case 'total_funding':
        $build = ThemeHelper::getThemeOptions('hpc_currency', $this->getValue(), $additional_theme_options);
        break;

      case 'coverage':
        $build = ThemeHelper::getThemeOptions('hpc_percent', $this->getValue(), $additional_theme_options);
        break;

      case 'requirements_changes':
        $build = ThemeHelper::getThemeOptions('hpc_currency', $this->getValue(), $additional_theme_options);
        break;
    }
    return $build;
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
   * Get the data types that this item can show.
   *
   * @return array
   *   An array of data types, keyed by their machine name, value is the label.
   */
  private function getDataTypes() {
    return [
      'original_requirements' => $this->t('Original requirements'),
      'current_requirements' => $this->t('Current requirements'),
      'total_funding' => $this->t('Current funding'),
      'coverage' => $this->t('Current coverage'),
      'requirements_changes' => $this->t('Requirements changes'),
    ];
  }

}
