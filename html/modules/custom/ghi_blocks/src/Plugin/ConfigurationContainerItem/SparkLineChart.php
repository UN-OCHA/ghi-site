<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;
use Drupal\ghi_plans\ApiObjects\Attachments\IndicatorAttachment;
use Drupal\hpc_common\Helpers\ThemeHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a sparkline chart item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "spark_line_chart",
 *   label = @Translation("Spark line chart"),
 *   description = @Translation("This item displays a spark line chart for multiple periods of a measurement data point."),
 * )
 */
class SparkLineChart extends ConfigurationContainerItemPluginBase {

  const ITEM_TYPE = 'chart';

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->attachmentQuery = $instance->endpointQueryManager->createInstance('attachment_query');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    $plan_object = $this->getContextValue('plan_object');

    $element['data_point_wrapper'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['data-point-wrapper'],
      ],
      '#description' => $this->t('Select the measurement data point for the spark line.'),
      '#tree' => FALSE,
    ];
    $element['data_point_wrapper']['data_point'] = [
      '#type' => 'select',
      '#title' => $this->t('Data point'),
      '#options' => $attachment_prototype->getMeasurementMetricFields(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'data_point'),
      '#parents' => array_merge($element['#parents'], ['data_point']),
    ];

    $element['data_point_wrapper']['monitoring_period'] = [
      '#type' => 'monitoring_period',
      '#title' => $this->t('Monitoring period'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'monitoring_period'),
      '#plan_id' => $plan_object->getSourceId(),
      '#parents' => array_merge($element['#parents'], ['monitoring_period']),
      '#add_wrapper' => FALSE,
    ];

    $default_checkbox = $this->get('use_calculation_method') ?? FALSE;
    $element['data_point_wrapper']['use_calculation_method'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use calculation method'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'use_calculation_method', $default_checkbox),
      '#parents' => array_merge($element['#parents'], ['use_calculation_method']),
      '#access' => $attachment_prototype->isIndicator(),
    ];
    if ($this->get('use_calculation_method') === NULL && $default_checkbox) {
      // Due to a bug with checkbox elements in ajax contexts, the default
      // value is not correctly set for new instances of a plugin. We catch
      // this situation by manually setting the checked attribute only if the
      // config key is still unset.
      // Might relate to https://www.drupal.org/project/drupal/issues/1100170.
      $element['use_calculation_method']['#attributes']['checked'] = 'checked';
    }
    $element['show_baseline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include goal line'),
      '#description' => $this->t('Check this to add an additional goal line for easier comparision of the progress.'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'show_baseline'),
    ];
    $baseline_selector = FormElementHelper::getStateSelector($element, ['show_baseline']);
    $element['baseline'] = [
      '#type' => 'select',
      '#title' => $this->t('Data point for goal line'),
      '#description' => $this->t('Select which data point should be used for the goal line.'),
      '#options' => $attachment_prototype->getFields(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'baseline'),
      '#states' => [
        'visible' => [
          'input[name="' . $baseline_selector . '"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLabel() {
    // Get the protoype, as that is where the labels come from.
    $attachment = $this->getContextValue('attachment');
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    if (!$attachment_prototype && $attachment instanceof DataAttachment) {
      $attachment_prototype = $attachment->getPrototype();
    }
    if (!$attachment_prototype instanceof AttachmentPrototype) {
      return NULL;
    }
    $data_point_options = $attachment_prototype->getMeasurementMetricFields();
    $data_point_index = $this->get('data_point') ?? array_key_first($data_point_options);

    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object') ?? NULL;
    return $attachment_prototype->getDefaultFieldLabel($data_point_index, $plan_object?->getPlanLanguage());
  }

  /**
   * Get the reporting periods to show for this element.
   *
   * @return \Drupal\ghi_plans\ApiObjects\PlanReportingPeriod[]
   *   An array of reporting period objects.
   */
  private function getReportingPeriods() {
    $attachment = $this->getAttachmentObject();
    return $attachment?->getReportingPeriods(NULL, $this->getConfiguredMonitoringPeriodId()) ?? [];
  }

  /**
   * Get the configured monitoring period id.
   *
   * @return int|string
   *   The configured monitoring period id or the first configured id of the
   *   monitoring periods array used in previous iterations of this
   *   configuration container item.
   *   If none can be found returns the string 'latest'.
   */
  private function getConfiguredMonitoringPeriodId() {
    $monitoring_period_id = $this->get('monitoring_period');
    if (!$monitoring_period_id) {
      $monitoring_period_ids = $this->get('monitoring_periods');
      $monitoring_period_id = is_array($monitoring_period_ids) ? reset($monitoring_period_ids) : NULL;
    }
    return $monitoring_period_id ?: 'latest';
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }
    $reporting_periods = $this->getReportingPeriods();

    $data_point_conf = [
      'processing' => 'single',
      'calculation' => 'substraction',
      'data_points' => [
        0 => [
          'index' => $this->get('data_point'),
          'monitoring_period' => array_key_last($reporting_periods),
        ],
        1 => ['index' => 0],
      ],
      'formatting' => 'auto',
      'widget' => 'none',
    ];
    return $attachment ? $attachment->getValue($data_point_conf) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    // Get some context.
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }

    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');

    // Get the configuration.
    $data_point = $this->get('data_point');
    $show_baseline = $this->get('show_baseline');
    $use_calculation_method = $this->get('use_calculation_method');
    $baseline = $show_baseline ? $this->get('baseline') : NULL;
    $options = $attachment->getMetricFields();
    $decimal_format = $plan_object->getDecimalFormat();

    // Get the monitoring periods.
    $reporting_periods = $this->getReportingPeriods();
    $last_reporting_period = $attachment->getLastNonEmptyReportingPeriod($data_point, $reporting_periods);
    $values = $attachment->getValuesForAllReportingPeriods($data_point, FALSE, TRUE, $reporting_periods);

    // Create the data / label arrays for all configured monitoring periods.
    $data = [];
    $tooltips = [];
    $accumulated_reporting_periods = [];
    foreach ($reporting_periods as $reporting_period) {
      if ($attachment instanceof IndicatorAttachment) {
        if ($use_calculation_method) {
          $accumulated_reporting_periods[$reporting_period->id()] = $reporting_period;
          $data[$reporting_period->id()] = $attachment->getSingleValue($data_point, $accumulated_reporting_periods);
        }
        else {
          $data[$reporting_period->id()] = $values[$reporting_period->id()] ?? NULL;
        }
      }
      else {
        // Caseloads.
        $data[$reporting_period->id()] = $attachment->getMeasurementMetricValue($data_point, $reporting_period->id());
      }

      // Check if this measurement is an actual NULL, in which case we want to
      // hide the tooltip.
      $null_measurement = $data[$reporting_period->id()] === NULL;
      if ($null_measurement) {
        $tooltips[$reporting_period->id()] = NULL;
        continue;
      }
      $totals = $attachment->values;

      // Prepare the tooltip items.
      $tooltip_items = [];

      // Add a baseline if needed.
      if ($show_baseline) {
        $tooltip_items[] = [
          'label' => $options[$baseline],
          'value' => [
            '#theme' => 'hpc_amount',
            '#amount' => $totals[$baseline],
            '#scale' => 'full',
            '#decimal_format' => $decimal_format,
          ],
        ];
      }
      $tooltip_items[] = [
        'label' => $options[$data_point],
        'value' => [
          '#theme' => 'hpc_amount',
          '#amount' => $data[$reporting_period->id()],
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
      ];

      // And theme the tooltip.
      $tooltips[$reporting_period->id()] = ThemeHelper::render([
        '#theme' => 'hpc_sparkline_tooltip',
        '#title' => Markup::create($reporting_period->format((string) $this->t('Monitoring period #@period_number<br />@date_range'))),
        '#items' => $tooltip_items,
      ], FALSE);
    }

    // Add a baseline if needed.
    if ($show_baseline) {
      $baseline_value = $attachment->getMeasurementMetricValue($baseline, $last_reporting_period?->id() ?? 'latest');
    }

    // Render the chart.
    return [
      '#theme' => 'hpc_sparkline',
      '#data' => $data,
      '#baseline_value' => $show_baseline ? $baseline_value : NULL,
      '#tooltips' => $tooltips,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();
    return $classes;
  }

  /**
   * Get the attachment object for this item.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment|null
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof DataAttachment ? $attachment : NULL;
  }

}
