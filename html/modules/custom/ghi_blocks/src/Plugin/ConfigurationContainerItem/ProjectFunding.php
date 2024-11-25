<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->projectFundingQuery = $instance->endpointQueryManager->createInstance('plan_project_funding_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $data_type_options = $this->getDataTypeOptions();
    $type_key = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);
    $data_type = $this->getType($type_key);

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

    $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
      '%default_label' => $this->getDefaultLabel($data_type),
    ]);
    $element['label']['#placeholder'] = $this->getDefaultLabel($data_type);
    $element['label']['#weight'] = 1;

    // Add a preview.
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getValue($type_key);
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
    $data_type = $data_type ?? $this->getType();
    return $data_type['default_label'] ?? ($data_type['label'] ?? NULL);
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
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');
    $additional_theme_options = [
      'decimal_format' => $plan_object->getDecimalFormat(),
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
   * Get the data type options.
   *
   * @return array
   *   An array of data types, suitable to use as options in a form element.
   */
  private function getDataTypeOptions() {
    $data_types = $this->getDataTypes();
    return array_map(function ($type) {
      return $type['label'];
    }, $data_types);
  }

  /**
   * Get the data types that this item can show.
   *
   * @return array
   *   An array of data types, keyed by their machine name, value is the label.
   */
  private function getDataTypes() {
    return [
      'original_requirements' => [
        'label' => $this->t('Original requirements'),
      ],
      'current_requirements' => [
        'label' => $this->t('Current requirements'),
      ],
      'total_funding' => [
        'label' => $this->t('Current funding'),
      ],
      'coverage' => [
        'label' => $this->t('Current coverage'),
        'default_label' => $this->t('% Funded'),
      ],
      'requirements_changes' => [
        'label' => $this->t('Requirements changes'),
      ],
    ];
  }

  /**
   * Get a specific data type definition.
   *
   * @param string $type
   *   The key of the type.
   *
   * @return array|null
   *   A definition array if the type is found.
   */
  private function getType($type = NULL) {
    $type = $type ?? $this->get('data_type');
    $types = $this->getDataTypes();
    return $type && array_key_exists($type, $types) ? $types[$type] : NULL;
  }

}
