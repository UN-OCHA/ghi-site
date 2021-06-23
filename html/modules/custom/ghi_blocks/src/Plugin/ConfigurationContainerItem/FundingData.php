<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Traits\ConfigurationItemClusterRestrictTrait;
use Drupal\ghi_blocks\Traits\FtsLinkTrait;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Query\ClusterQuery;
use Drupal\ghi_plans\Query\FlowSearchQuery;
use Drupal\ghi_plans\Query\PlanClusterSummaryQuery;
use Drupal\ghi_plans\Query\PlanFundingSummaryQuery;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\node\NodeInterface;

/**
 * Provides an funding data item for configuration containers.
 *
 * This item type allows the following options when using as part of a
 * configuration container:
 * - cluster_restrict: When set and set to FALSE, this disables the additional
 *   cluster restriction form element in configuration.
 *
 * @todo This is still missing support for special requirements logic.
 *
 * @ConfigurationContainerItem(
 *   id = "funding_data",
 *   label = @Translation("Financial data"),
 *   description = @Translation("Using the Financial data item, you can add funding and requirements data to this block. You can choose between different ways of displaying the data and do calculations. You can also override the default label."),
 * )
 */
class FundingData extends ConfigurationContainerItemPluginBase {

  use ConfigurationItemClusterRestrictTrait;
  use ConfigurationItemValuePreviewTrait;
  use FtsLinkTrait;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\PlanFundingSummaryQuery
   */
  public $fundingSummaryQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\PlanClusterSummaryQuery
   */
  public $planClusterSummaryQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\FlowSearchQuery
   */
  public $flowSearchQuery;

  /**
   * The funding query.
   *
   * @var \Drupal\ghi_plans\Query\ClusterQuery
   */
  public $clusterQuery;

  /**
   * Flag fro disabling the FTS link for an instance.
   *
   * @var bool
   */
  private $ftsLinkDisabled = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PlanFundingSummaryQuery $funding_summary_query, PlanClusterSummaryQuery $plan_cluster_summary_query, FlowSearchQuery $flow_search_query, ClusterQuery $cluster_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->fundingSummaryQuery = $funding_summary_query;
    $this->planClusterSummaryQuery = $plan_cluster_summary_query;
    $this->flowSearchQuery = $flow_search_query;
    $this->clusterQuery = $cluster_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.plan_funding_summary_query'),
      $container->get('ghi_plans.plan_cluster_summary_query'),
      $container->get('ghi_plans.flow_search_query'),
      $container->get('ghi_plans.cluster_query'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $context = $this->getContext();
    $context_node = $context['context_node'];

    $data_type_options = $this->getDataTypeOptions();
    $data_type_key = $this->getSubmittedOptionsValue($element, $form_state, 'data_type', $data_type_options);
    $scale = $this->getSubmittedValue($element, $form_state, 'scale', 'auto');
    $cluster_restrict = $this->getSubmittedValue($element, $form_state, 'cluster_restrict', [
      'type' => NULL,
      'tag' => NULL,
    ]);

    $element['data_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Data type'),
      '#options' => $data_type_options,
      '#default_value' => $data_type_key,
      '#weight' => 0,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
    ];
    $element['label']['#weight'] = 1;

    $data_type = $this->getDataType($data_type_key);
    if ($data_type && !empty($data_type['default_label'])) {
      $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
        '%default_label' => (string) $data_type['default_label'],
      ]);
      $element['label']['#placeholder'] = (string) $data_type['default_label'];
    }
    else {
      $element['label']['#required'] = TRUE;
    }

    if ($context_node && $context_node->bundle() == 'plan' && $data_type['cluster_restrict'] && !$this->clusterRestrictDisabled()) {
      $element['cluster_restrict'] = $this->buildClusterRestrictFormElement($cluster_restrict);
    }

    $element['scale'] = [
      '#type' => 'select',
      '#title' => $this->t('Scale'),
      '#options' => [
        'auto' => $this->t('Automatic'),
        'full' => $this->t('Full value'),
      ],
      '#default_value' => $scale,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $this->wrapperId,
      ],
      '#weight' => 2,
    ];

    if ($data_type && !empty($data_type['scale'])) {
      $element['scale']['#type'] = 'hidden';
      $element['scale']['#value'] = $data_type['scale'];
      $element['scale']['#default_value'] = $data_type['scale'];
    }

    // Add a preview.
    if ($this->shouldDisplayPreview()) {
      $preview_value = $this->getRenderArray($data_type_key, $scale, $cluster_restrict);
      $element['value_preview'] = $this->buildValuePreviewFormElement($preview_value);
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    if (!empty($this->config['label'])) {
      return $this->config['label'];
    }
    $data_type = $this->getDataType();
    return $data_type['default_label'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue($data_type_key = NULL, $scale = NULL, $cluster_restrict = NULL) {
    $context = $this->getContext();
    $context_node = $context['context_node'];

    $data_type = $this->getDataType($data_type_key ?: $this->get('data_type'));
    $property = $data_type['property'];
    $cluster_restrict = $cluster_restrict ?: ($this->get('cluster_restrict') ?: NULL);

    $value = NULL;
    if (empty($context['context_node']) && !empty($context['entity'])) {
      return $this->planClusterSummaryQuery->getClusterProperty($context['entity'], $property, 0);
    }
    if (!$context_node) {
      return $value;
    }
    if ($context_node->bundle() == 'plan') {
      if (!empty($cluster_restrict) && !empty($cluster_restrict['type']) && $cluster_restrict['type'] != 'none') {
        $value = $this->getValueWithClusterRestrict($data_type, $cluster_restrict);
      }
      else {
        $value = $this->fundingSummaryQuery->get($property, 0);
      }
    }
    elseif ($context_node->bundle() == 'governing_entity') {
      $cluster_id = $context_node->field_original_id->value;
      $value = $this->planClusterSummaryQuery->getClusterPropertyById($cluster_id, $property, 0);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray($data_type_key = NULL, $scale = NULL, $cluster_restrict = NULL) {
    $data_type = $this->getDataType($data_type_key ?: $this->get('data_type'));
    $scale = ($scale ?: $this->get('scale')) ?: (!empty($data_type['scale']) ? $data_type['scale'] : 'auto');
    $cluster_restrict = $cluster_restrict ?: ($this->get('cluster_restrict') ?: NULL);

    $theme_function = !empty($data_type['theme']) ? $data_type['theme'] : 'hpc_currency';
    $theme_options = !empty($data_type['theme_options']) ? $data_type['theme_options'] : [];

    $rendered = [
      '#theme' => $theme_function,
    ] + ThemeHelper::getThemeOptions($theme_function, $this->getValue($data_type_key, $scale, $cluster_restrict), [
      'scale' => $scale,
      'formatting_decimals' => $this->getContextValue('plan_node')->field_decimal_format->value,
    ] + $theme_options);

    if (!$this->needsFtsLink()) {
      return $rendered;
    }

    // If this needs an FTS link, lets build and add that.
    $link_icon = ThemeHelper::themeFtsIcon();
    $fts_link = $this->needsFtsLink() ? self::buildFtsLink($link_icon, $this->getContextValue('plan_node'), 'flows', $this->getContextValue('context_node')) : NULL;

    return [
      '#type' => 'container',
      0 => $rendered,
      1 => $fts_link,
    ];
  }

  /**
   * Check if this item needs an FTS link.
   *
   * @return bool
   *   TRUE if a link is needed, FALSE otherwhise.
   */
  private function needsFtsLink() {
    $plugin_configuration = $this->getPluginConfiguration();
    if (array_key_exists('fts_link', $plugin_configuration) && $plugin_configuration['fts_link'] !== TRUE) {
      // Explicitely requested to skip the link.
      return FALSE;
    }
    if ($this->ftsLinkDisabled) {
      return FALSE;
    }
    // All items except the progress bar can have links to FTS.
    return $this->get('data_type') != 'funding_progress_bar';
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
   * Get a value using the configured cluster restrict.
   *
   * @param array $data_type
   *   A data type definition.
   * @param array $cluster_restrict
   *   A cluster restriction to apply.
   *
   * @return mixed|null
   *   The retrieved value.
   */
  public function getValueWithClusterRestrict(array $data_type, array $cluster_restrict) {

    $context = $this->getContext();
    $plan_node = $context['plan_node'];
    $plan_id = $plan_node->field_original_id->value;

    // Extract the actually used cluster from the funding and requirements data.
    $search_results = $this->flowSearchQuery->search([
      'planid' => $plan_id,
      'groupby' => 'cluster',
    ]);

    $cluster_ids = $this->getClusterIdsByClusterRestrict($cluster_restrict, $search_results, $this->clusterQuery);
    $data = $this->flowSearchQuery->getFundingDataByClusterIds($search_results, $cluster_ids);
    return array_key_exists($data_type['property'], $data) ? $data[$data_type['property']] : NULL;
  }

  /**
   * Get the data type options.
   *
   * @return array
   *   An array of data types, suitable to use as options in a form element.
   */
  private function getDataTypeOptions() {
    $context = $this->getContext();
    $context_node = $context['context_node'];
    $data_types = array_filter($this->getDataTypes(), function ($type) use ($context_node) {
      return !array_key_exists('valid_context', $type) || ($context_node instanceof NodeInterface && in_array($context_node->bundle(), $type['valid_context']));
    });
    return array_map(function ($type) {
      return $type['title'];
    }, $data_types);
  }

  /**
   * Get the available data types.
   *
   * @return array
   *   An array of defined data types.
   */
  private function getDataTypes() {
    $available_types = [
      'funding_totals' => [
        'title' => $this->t('Funding totals'),
        'default_label' => $this->t('Current funding ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'total_funding',
        'scale' => 'auto',
      ],
      'outside_funding' => [
        'title' => $this->t('Funded outside HRP'),
        'default_label' => $this->t('Funded outside HRP ($)'),
        'valid_context' => ['plan'],
        'cluster_restrict' => FALSE,
        'property' => 'outside_funding',
        'scale' => 'auto',
      ],
      'funding_coverage' => [
        'title' => $this->t('Coverage (%)'),
        'default_label' => $this->t('Coverage'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_coverage',
        'theme' => 'hpc_percent',
      ],
      'funding_progress_bar' => [
        'title' => $this->t('Funding coverage progress bar'),
        'default_label' => $this->t('Funding coverage'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_coverage',
        'theme' => 'hpc_progress_bar',
        'theme_options' => [
          'hide_value' => TRUE,
        ],
      ],
      'funding_gap' => [
        'title' => $this->t('Funding gap'),
        'default_label' => $this->t('Unmet ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'funding_gap',
        'scale' => 'auto',
      ],
      'original_requirements' => [
        'title' => $this->t('Original requirements'),
        'default_label' => $this->t('Original ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'original_requirements',
      ],
      'current_requirements' => [
        'title' => $this->t('Current requirements'),
        'default_label' => $this->t('Requirements ($)'),
        'valid_context' => ['plan', 'governing_entity'],
        'cluster_restrict' => TRUE,
        'property' => 'current_requirements',
        // @todo Add support for inclusion of original requirements as a
        // tooltip.
      ],
    ];
    $configuration = $this->getPluginConfiguration();
    if (array_key_exists('item_types', $configuration)) {
      $available_types = array_intersect_key($available_types, array_flip($configuration['item_types']));
    }
    return $available_types;
  }

  /**
   * Get a specific data type definition.
   *
   * @param string $data_type
   *   The key of the data type.
   *
   * @return array|null
   *   A definition array if the data type is found.
   */
  private function getDataType($data_type = NULL) {
    if ($data_type === NULL) {
      $data_type = $this->config['data_type'] ?? $this->config['data_type'];
    }
    $data_types = $this->getDataTypes();
    return array_key_exists($data_type, $data_types) ? $data_types[$data_type] : NULL;
  }

  /**
   * Whether cluster restriction is disabled.
   *
   * @return bool
   *   TRUE if cluster restriction is disabled, FALSE otherwhise.
   */
  private function clusterRestrictDisabled() {
    $plugin_configuration = $this->getPluginConfiguration();
    return array_key_exists('cluster_restrict', $plugin_configuration) && $plugin_configuration['cluster_restrict'] === FALSE;
  }

  /**
   * Explicitely disable the FTS link for an instance of this plugin.
   */
  public function disableFtsLink() {
    $this->ftsLinkDisabled = TRUE;
  }

}
