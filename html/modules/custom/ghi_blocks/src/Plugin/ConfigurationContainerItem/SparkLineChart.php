<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;
use Drupal\hpc_common\Helpers\ThemeHelper;

/**
 * Provides an sparkline chart item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "spark_line_chart",
 *   label = @Translation("Spark line chart"),
 *   description = @Translation("This item displays a spark line chart for multiple periods of a metric or measurement item."),
 * )
 */
class SparkLineChart extends ConfigurationContainerItemPluginBase {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EndpointQueryManager $endpoint_query_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $endpoint_query_manager);
    $this->attachmentQuery = $endpoint_query_manager->createInstance('attachment_query');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');

    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $this->getContextValue('attachment_prototype');

    $element['data_point'] = [
      '#type' => 'select',
      '#title' => $this->t('Data point'),
      '#options' => $attachment_prototype->getMeasurementMetricFields(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'data_point'),
    ];
    $element['monitoring_periods'] = [
      '#type' => 'monitoring_period',
      '#title' => $this->t('Monitoring periods'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'monitoring_periods'),
      '#multiple' => TRUE,
      '#plan_id' => $plan_object->getSourceId(),
    ];
    $element['include_latest_period'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always include the latest measurement'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'include_latest_period'),
    ];
    $element['show_baseline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include goal line'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'show_baseline'),
    ];
    $element['baseline'] = [
      '#type' => 'select',
      '#title' => $this->t('Data point'),
      '#options' => $attachment_prototype->getFields(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'baseline'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultLabel() {
    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    $attachment = $this->getContextValue('attachment');
    $data_point_options = $attachment_prototype->getMeasurementMetricFields();
    $data_point = $this->get('data_point') ?? array_key_first($data_point_options);
    if ($attachment) {
      return $attachment->fields[$data_point];
    }
    $attachment_prototype = $this->getContextValue('attachment_prototype');
    $fields = array_merge($attachment_prototype->fields ?? []);
    return $fields[$data_point];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $attachment = $this->getAttachmentObject();
    $monitoring_periods = $this->get('monitoring_periods');
    $attachment->data_point_conf = [
      'processing' => 'single',
      'calculation' => 'substraction',
      'monitoring_period' => end($monitoring_periods),
      'data_points' => [
        0 => $this->get('data_point'),
        1 => 0,
      ],
      'formatting' => 'auto',
      'widget' => 'none',
    ];
    return $attachment ? DataPointHelper::getValue($attachment, $attachment->data_point_conf) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    // Get some context.
    /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
    $attachment = $this->getAttachmentObject();
    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');

    // Get the configuration.
    $data_point = $this->get('data_point');
    $monitoring_periods = $this->get('monitoring_periods');
    $include_latest_period = $this->get('include_latest_period');
    $show_baseline = $this->get('show_baseline');
    $baseline = $show_baseline ? $this->get('baseline') : NULL;
    $options = $attachment->getMetricFields();
    $decimal_format = $plan_object->getDecimalFormat();

    // Get the monitoring periods.
    $reporting_periods = $attachment->getReportingPeriods($plan_object->getSourceId(), TRUE);
    $last_reporting_period = end($reporting_periods);

    // Create the data / label arrays for all configured monitoring periods.
    $data = [];
    foreach ($reporting_periods as $reporting_period) {
      if (is_array($monitoring_periods) && !in_array($reporting_period->id, $monitoring_periods)) {
        continue;
      }
      $data[$reporting_period->id] = $attachment->getMeasurementMetricValue($data_point, $reporting_period->id);
      $totals = $attachment->values;

      // Prepare the tooltip items.
      $tooltip_items = [];
      $tooltip_format = 'Monitoring period #@period_number<br />@date_range';

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
          '#amount' => $data[$reporting_period->id],
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
      ];

      // And theme the tooltip.
      $tooltips[$reporting_period->id] = ThemeHelper::render([
        '#theme' => 'hpc_sparkline_tooltip',
        '#title' => [
          '#theme' => 'hpc_reporting_period',
          '#reporting_period' => $reporting_period,
          '#format_string' => $tooltip_format,
        ],
        '#items' => $tooltip_items,
      ], FALSE);
    }

    // Add in the latest measurement if not yet present.
    if ($include_latest_period && empty($data[$last_reporting_period->id])) {
      $data[$last_reporting_period->id] = $attachment->getMeasurementMetricValue($data_point, $last_reporting_period->id);

      // Prepare the tooltip item.
      $tooltip_items = [];

      $tooltip_items[] = [
        'label' => $options[$data_point],
        'value' => [
          '#theme' => 'hpc_amount',
          '#amount' => $data[$last_reporting_period->id],
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ],
      ];

      $tooltips[$last_reporting_period->id] = ThemeHelper::render([
        '#theme' => 'hpc_sparkline_tooltip',
        '#title' => [
          '#theme' => 'hpc_reporting_period',
          '#reporting_period' => $last_reporting_period,
          '#format_string' => $tooltip_format,
        ],
        '#items' => $tooltip_items,
      ], FALSE);
    }

    // Add a baseline if needed.
    if ($show_baseline) {
      $baseline_value = $attachment->getMeasurementMetricValue($baseline, $last_reporting_period->id);
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
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment;
  }

}
