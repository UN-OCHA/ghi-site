<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Markup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_blocks\Traits\ConfigurationItemValuePreviewTrait;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\ghi_plans\PlanMetricResolver;
use Drupal\ghi_plans\ValueObject\PlanMetricValues;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Drupal\hpc_common\Traits\RenderArrayTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays a normalized plan metric for the plan's configured source.
 *
 * Missing source-selectable metrics remain empty instead of falling back to
 * the other source. Funding always comes from imported FTS data.
 */
#[ConfigurationContainerItem(
  id: 'plan_metric_data',
  label: new TranslatableMarkup('Plan metric'),
  description: new TranslatableMarkup("This item displays a plan-level metric using the plan's configured data source and imported FTS funding."),
)]
class PlanMetricData extends ConfigurationContainerItemPluginBase {

  /**
   * Metric names that can be selected by this item type.
   */
  public const SUPPORTED_METRICS = [
    'total_population',
    'in_need',
    'target',
    'prioritized_target',
    'expected_reach',
    'latest_reach',
    'requirements',
    'prioritized_requirements',
    'original_requirements',
    'funding',
    'outside_funding',
    'funding_gap',
    'coverage',
  ];

  /**
   * Metrics that use currency formatting by default.
   */
  private const FINANCIAL_METRICS = [
    'requirements',
    'prioritized_requirements',
    'original_requirements',
    'funding',
    'outside_funding',
    'funding_gap',
  ];

  use ConfigurationItemValuePreviewTrait;
  use RenderArrayTrait;

  /**
   * The source-aware plan metric resolver.
   */
  protected PlanMetricResolver $planMetricResolver;

  /**
   * The resolved values for this plugin instance.
   */
  private ?PlanMetricValues $metricValues = NULL;

  /**
   * Whether resolving has already been attempted.
   *
   * This also caches a legitimate empty result for the plugin instance.
   */
  private bool $valuesResolved = FALSE;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): PlanMetricData {
    /** @var self $instance */
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->planMetricResolver = $container->get('ghi_plans.plan_metric_resolver');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $metric_options = $this->getMetricOptions();
    $flat_metric_options = OptGroup::flattenOptions($metric_options);
    $processing = $this->getSubmittedOptionsValue($element, $form_state, 'processing', $this->getProcessingOptions());
    $metric = $this->getSubmittedOptionsValue($element, $form_state, 'metric', $flat_metric_options);
    $metric_2 = $this->getSubmittedOptionsValue($element, $form_state, 'metric_2', $flat_metric_options);
    $calculation = $this->getSubmittedOptionsValue($element, $form_state, 'calculation', $this->getCalculationOptions());
    $formatting = $this->getSubmittedOptionsValue($element, $form_state, 'formatting', $this->getFormattingOptions());
    $ajax = [
      'event' => 'change',
      'callback' => [static::class, 'updateAjax'],
      'wrapper' => $this->wrapperId,
    ];
    $processing_selector = FormElementHelper::getStateSelector($element, ['processing']);
    $source_label = $this->getPlanDataSourceLabel();
    $element['#attached']['library'][] = 'hpc_common/icon';
    $element['#attached']['library'][] = 'hpc_common/tooltip';
    if ($source_label) {
      $element['metric_source'] = [
        '#type' => 'item',
        '#title' => $this->buildTitleWithTooltip($this->t('Current plan metric source'), $this->t('This is a plan-wide setting. Change it on the Plan metrics tab of the plan base object.')),
        '#markup' => $source_label,
        '#weight' => -6,
      ];
    }
    $element['processing'] = [
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => $this->getProcessingOptions(),
      '#default_value' => $processing,
      '#weight' => -5,
      '#ajax' => $ajax,
    ];
    $element['metric'] = [
      '#type' => 'select',
      '#title' => $this->buildTitleWithTooltip(
        $processing === DataPointHelper::PROCESSING_CALCULATED ? $this->t('Metric #1') : $this->t('Metric'),
        $this->t("Available values depend on the plan's configured metric source. Funding always uses imported FTS data; other metrics remain empty when the configured source does not provide them."),
      ),
      '#options' => $metric_options,
      '#default_value' => $metric,
      '#weight' => -4,
      '#ajax' => $ajax,
    ];
    $element['metric_2'] = [
      '#type' => 'select',
      '#title' => $this->t('Metric #2'),
      '#options' => $metric_options,
      '#default_value' => $metric_2,
      '#weight' => -3,
      '#ajax' => $ajax,
      '#states' => [
        'visible' => [
          ':input[name="' . $processing_selector . '"]' => ['value' => DataPointHelper::PROCESSING_CALCULATED],
        ],
      ],
    ];
    $element['calculation'] = [
      '#type' => 'select',
      '#title' => $this->t('Calculation'),
      '#options' => $this->getCalculationOptions(),
      '#default_value' => $calculation,
      '#weight' => -2,
      '#ajax' => $ajax,
      '#states' => [
        'visible' => [
          ':input[name="' . $processing_selector . '"]' => ['value' => DataPointHelper::PROCESSING_CALCULATED],
        ],
      ],
    ];
    $element['formatting'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatting'),
      '#options' => $this->getFormattingOptions(),
      '#default_value' => $formatting,
      '#weight' => -1,
      '#ajax' => $ajax,
    ];
    $element['label']['#description'] = $this->t('Leave empty to use the default label: <em>%default_label</em>', [
      '%default_label' => $this->getDefaultLabel($metric),
    ]);
    $element['label']['#placeholder'] = $this->getDefaultLabel($metric);
    if ($this->shouldDisplayPreview()) {
      $preview_configuration = [
        'metric' => $metric,
        'processing' => $processing,
        'metric_2' => $metric_2,
        'calculation' => $calculation,
        'formatting' => $formatting,
      ];
      $preview_configuration = $this->getMetricConfiguration($preview_configuration);
      $element['value_preview'] = $this->buildValuePreviewFormElement($this->getRenderArrayForConfiguration($preview_configuration));
      if ($source_label && $this->getValueForConfiguration($preview_configuration) === NULL) {
        $element['value_preview']['#description'] = $this->t('No value is currently available for this metric from @source.', [
          '@source' => $source_label,
        ]);
      }
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return !empty($this->config['label']) ? trim($this->config['label']) : $this->getDefaultLabel();
  }

  /**
   * Gets the default label for a metric.
   */
  public function getDefaultLabel(?string $metric = NULL): ?string {
    $metric = $metric ?? $this->get('metric');
    return $this->getMetricDefaultLabels()[$metric] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->getValueForConfiguration($this->getMetricConfiguration());
  }

  /**
   * Gets the value for a normalized metric configuration.
   */
  private function getValueForConfiguration(array $configuration): int|float|null {
    $metric = $configuration['metric'];
    $values = $this->getMetricValues();
    if (!$values || !$metric) {
      return NULL;
    }
    $value = $this->getMetricValue($values, $metric);
    if ($configuration['processing'] !== DataPointHelper::PROCESSING_CALCULATED) {
      return $value;
    }
    $metric_2 = $configuration['metric_2'];
    return DataPointHelper::calculate($value, $metric_2 ? $this->getMetricValue($values, $metric_2) : NULL, $configuration['calculation']);
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    return $this->getRenderArrayForConfiguration($this->getMetricConfiguration());
  }

  /**
   * Gets the render array for a normalized metric configuration.
   */
  private function getRenderArrayForConfiguration(array $configuration): array {
    $plan = $this->getPlan();
    $value = $this->getValueForConfiguration($configuration);
    if ($configuration['formatting'] === DataPointHelper::FORMATTING_RAW) {
      return $this->applyCacheTags(['#plain_text' => $value !== NULL ? (string) $value : '']);
    }
    $theme = $this->getThemeForConfiguration($configuration);
    if ($theme === 'hpc_percent' && $value !== NULL) {
      $value *= 100;
    }
    $options = [
      'decimal_format' => $plan?->getDecimalFormat(),
    ];
    if ($configuration['formatting'] === DataPointHelper::FORMATTING_AMOUNT) {
      $options['scale'] = 'full';
    }
    elseif ($configuration['formatting'] === DataPointHelper::FORMATTING_AMOUNT_ROUNDED || ($configuration['formatting'] === DataPointHelper::FORMATTING_AUTO && $theme === 'hpc_amount')) {
      $options['decimals'] = 1;
    }
    elseif ($configuration['formatting'] === DataPointHelper::FORMATTING_CURRENCY) {
      $options['decimals'] = 2;
    }
    return $this->applyCacheTags($this->buildRenderArray($theme, $value, $options));
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags($this->getPlan()?->getCacheTags() ?? [], $this->getMetricValues()?->getCacheTags() ?? []);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationErrors() {
    $errors = [];
    $metric_options = $this->getFlatMetricOptions();
    if (!$this->getPlan()) {
      $errors[] = (string) $this->t('No plan available');
    }
    if (!array_key_exists($this->get('metric'), $metric_options)) {
      $errors[] = (string) $this->t('No plan-level metric configured');
    }
    if (!array_key_exists($this->getProcessing(), $this->getProcessingOptions())) {
      $errors[] = (string) $this->t('Invalid processing type configured');
    }
    if ($this->getProcessing() === DataPointHelper::PROCESSING_CALCULATED) {
      if (!array_key_exists($this->get('metric_2'), $metric_options)) {
        $errors[] = (string) $this->t('No second plan-level metric configured');
      }
      if (!array_key_exists($this->get('calculation'), $this->getCalculationOptions())) {
        $errors[] = (string) $this->t('No calculation configured');
      }
    }
    if (!array_key_exists($this->getFormatting(), $this->getFormattingOptions())) {
      $errors[] = (string) $this->t('Invalid formatting configured');
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function getDataAttributes() {
    $attributes = parent::getDataAttributes();
    $attributes['data-metric-type'] = $this->get('metric');
    if ($this->getProcessing() === DataPointHelper::PROCESSING_CALCULATED) {
      $attributes['data-secondary-metric-type'] = $this->get('metric_2');
      $attributes['data-calculation'] = $this->get('calculation');
    }
    $attributes['data-plan-data-source'] = $this->getMetricValues()?->getSource();
    return $attributes;
  }

  /**
   * Gets a metric value, including derived plan-level metrics.
   */
  private function getMetricValue(PlanMetricValues $values, string $metric): int|float|null {
    if (!in_array($metric, ['coverage', 'funding_gap'], TRUE)) {
      return $values->get($metric);
    }
    $requirements = $values->get('requirements');
    $funding = $values->get('funding');
    if ($requirements === NULL || $funding === NULL) {
      return NULL;
    }
    return $metric === 'coverage' ? CommonHelper::calculateRatio($funding, $requirements) : $requirements - $funding;
  }

  /**
   * Gets the processing mode with a backwards-compatible default.
   */
  private function getProcessing(): string {
    return $this->get('processing') ?: DataPointHelper::PROCESSING_SINGLE;
  }

  /**
   * Gets the formatting mode with a backwards-compatible default.
   */
  private function getFormatting(): string {
    return $this->get('formatting') ?: DataPointHelper::FORMATTING_AUTO;
  }

  /**
   * Gets a normalized metric configuration with backwards-compatible defaults.
   */
  private function getMetricConfiguration(array $overrides = []): array {
    return $overrides + [
      'metric' => $this->get('metric'),
      'processing' => $this->getProcessing(),
      'metric_2' => $this->get('metric_2'),
      'calculation' => $this->get('calculation'),
      'formatting' => $this->getFormatting(),
    ];
  }

  /**
   * Selects the render theme for a normalized metric configuration.
   */
  private function getThemeForConfiguration(array $configuration): string {
    if ($configuration['formatting'] !== DataPointHelper::FORMATTING_AUTO) {
      return match ($configuration['formatting']) {
        DataPointHelper::FORMATTING_CURRENCY => 'hpc_currency',
        DataPointHelper::FORMATTING_PERCENT => 'hpc_percent',
        default => 'hpc_amount',
      };
    }
    if ($configuration['processing'] === DataPointHelper::PROCESSING_CALCULATED) {
      // A percentage is dimensionless. Other calculations retain a currency
      // format only when both operands are financial metrics.
      if ($configuration['calculation'] === DataPointHelper::CALCULATION_PERCENTAGE) {
        return 'hpc_percent';
      }
      if (in_array($configuration['metric'], self::FINANCIAL_METRICS, TRUE) && in_array($configuration['metric_2'], self::FINANCIAL_METRICS, TRUE)) {
        return 'hpc_currency';
      }
      return 'hpc_amount';
    }
    return in_array($configuration['metric'], self::FINANCIAL_METRICS, TRUE) ? 'hpc_currency' : ($configuration['metric'] === 'coverage' ? 'hpc_percent' : 'hpc_amount');
  }

  /**
   * Resolves the current plan's normalized metrics.
   */
  private function getMetricValues(): ?PlanMetricValues {
    if ($this->valuesResolved) {
      return $this->metricValues;
    }
    $this->valuesResolved = TRUE;
    $plan = $this->getPlan();
    $plan_id = $plan?->getSourceId();
    if (!$plan || !$plan_id) {
      return NULL;
    }
    $values = $this->planMetricResolver->resolve([$plan_id => $plan]);
    $this->metricValues = $values[$plan_id] ?? NULL;
    return $this->metricValues;
  }

  /**
   * Gets the plan from the configuration item context.
   */
  private function getPlan(): ?Plan {
    $plan = $this->getContextValue('plan_object');
    return $plan instanceof Plan ? $plan : NULL;
  }

  /**
   * Gets the human-readable source configured on the current plan.
   */
  private function getPlanDataSourceLabel(): ?TranslatableMarkup {
    return match ($this->getPlan()?->getPlanDataSource()) {
      Plan::DATA_SOURCE_PLAN => $this->t('Country Module data'),
      Plan::DATA_SOURCE_GHO => $this->t('GHO data'),
      default => NULL,
    };
  }

  /**
   * Builds a form element title with a help tooltip.
   */
  private function buildTitleWithTooltip(TranslatableMarkup $title, TranslatableMarkup $tooltip): FormattableMarkup {
    $tooltip = ThemeHelper::render([
      '#theme' => 'hpc_tooltip',
      '#tooltip' => $tooltip,
      '#tag_content' => [
        '#theme' => 'hpc_icon',
        '#icon' => 'help',
        '#tag' => 'span',
      ],
    ], FALSE);
    return new FormattableMarkup('<span class="plan-metric-form-label">@title @tooltip</span>', [
      '@title' => $title,
      '@tooltip' => Markup::create($tooltip),
    ]);
  }

  /**
   * Gets the supported normalized plan metrics.
   */
  private function getMetricOptions(): array {
    return [
      (string) $this->t('Population data') => [
        'total_population' => $this->t('Total population'),
        'in_need' => $this->t('People in need'),
        'target' => $this->t('People targeted'),
        'prioritized_target' => $this->t('People prioritized'),
        'expected_reach' => $this->t('People expected to be reached'),
        'latest_reach' => $this->t('People reached'),
      ],
      (string) $this->t('Financial data') => [
        'requirements' => $this->t('Requirements ($)'),
        'prioritized_requirements' => $this->t('Prioritized requirements ($)'),
        'original_requirements' => $this->t('Original requirements ($)'),
        'funding' => $this->t('Current funding ($)'),
        'outside_funding' => $this->t('Funded outside HRP ($)'),
        'funding_gap' => $this->t('Funding gap ($)'),
        'coverage' => $this->t('Funding coverage (%)'),
      ],
    ];
  }

  /**
   * Gets the metric options without their presentation groups.
   */
  private function getFlatMetricOptions(): array {
    return OptGroup::flattenOptions($this->getMetricOptions());
  }

  /**
   * Gets default display labels, preserving the legacy financial labels.
   */
  private function getMetricDefaultLabels(): array {
    return array_replace($this->getFlatMetricOptions(), [
      'original_requirements' => $this->t('Original ($)'),
      'funding_gap' => $this->t('Unmet ($)'),
      'coverage' => $this->t('% Funded'),
    ]);
  }

  /**
   * Gets the supported processing modes.
   */
  private function getProcessingOptions(): array {
    return DataPointHelper::getProcessingOptions();
  }

  /**
   * Gets the supported calculations.
   */
  private function getCalculationOptions(): array {
    return DataPointHelper::getCalculationOptions();
  }

  /**
   * Gets the supported formatting modes.
   */
  private function getFormattingOptions(): array {
    return DataPointHelper::getFormattingOptions($this->t('Automatic'));
  }

}
