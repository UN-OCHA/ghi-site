<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
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

    /** @var \Drupal\ghi_plans\Entity\Plan $plan_object */
    $plan_object = $this->getContextValue('plan_object');

    /** @var \Drupal\ghi_plans\ApiObjects\AttachmentPrototype\AttachmentPrototype $attachment_prototype */
    $attachment_prototype = $this->getContextValue('attachment_prototype');

    $element['data_point'] = [
      '#type' => 'select',
      '#title' => $this->t('Data point'),
      '#description' => $this->t('Select the measurement data point for the spark line.'),
      '#options' => $attachment_prototype->getMeasurementMetricFields(),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'data_point'),
    ];

    $default_checkbox = $this->get('use_calculation_method') ?? TRUE;
    $element['use_calculation_method'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use calculation method'),
      '#description' => $this->t('If checked, the values for the data points will be calculated according to the calculation method set in RPM.'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'use_calculation_method', $default_checkbox),
      '#access' => $attachment_prototype->isIndicator(),
    ];
    if ($this->get('use_calculation_method') === NULL) {
      // Due to a bug with checkbox elements in ajax contexts, the default
      // value is not correctly set for new instances of a plugin. We catch
      // this situation by manually setting the checked attribute only if the
      // config key is still unset.
      // Might relate to https://www.drupal.org/project/drupal/issues/1100170.
      $element['use_calculation_method']['#attributes']['checked'] = 'checked';
    }
    $element['monitoring_periods'] = [
      '#type' => 'monitoring_periods',
      '#title' => $this->t('Monitoring periods'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'monitoring_periods'),
      '#default_all' => TRUE,
      '#plan_id' => $plan_object->getSourceId(),
      '#required' => TRUE,
      '#access' => !$attachment_prototype->isIndicator(),
    ];
    $element['include_latest_period'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Always include the latest measurement'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'include_latest_period'),
      '#access' => !$attachment_prototype->isIndicator(),
    ];
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
    if (!$attachment) {
      return NULL;
    }
    $monitoring_periods = $this->get('monitoring_periods');
    $data_point_conf = [
      'processing' => 'single',
      'calculation' => 'substraction',
      'data_points' => [
        0 => [
          'index' => $this->get('data_point'),
          'monitoring_period' => end($monitoring_periods),
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
    $monitoring_periods = $this->get('monitoring_periods');
    $include_latest_period = $this->get('include_latest_period');
    $show_baseline = $this->get('show_baseline');
    $use_calculation_method = $this->get('use_calculation_method');
    $baseline = $show_baseline ? $this->get('baseline') : NULL;
    $options = $attachment->getMetricFields();
    $decimal_format = $plan_object->getDecimalFormat();

    // Get the monitoring periods.
    $reporting_periods = $attachment->getPlanReportingPeriods($plan_object->getSourceId(), TRUE);
    $last_reporting_period = $attachment->getLastNonEmptyReportingPeriod($data_point, $reporting_periods);
    $values = $attachment->getValuesForAllReportingPeriods($data_point, FALSE, TRUE, $reporting_periods);

    // Create the data / label arrays for all configured monitoring periods.
    $data = [];
    $tooltips = [];
    $accumulated_reporting_periods = [];
    foreach ($reporting_periods as $reporting_period) {
      if (!array_key_exists($reporting_period->id, $values)) {
        continue;
      }
      if (!$attachment instanceof IndicatorAttachment && is_array($monitoring_periods) && !in_array($reporting_period->id, $monitoring_periods)) {
        continue;
      }
      if ($attachment instanceof IndicatorAttachment) {
        if ($use_calculation_method) {
          $accumulated_reporting_periods[$reporting_period->id] = $reporting_period;
          $data[$reporting_period->id] = $attachment->getSingleValue($data_point, $accumulated_reporting_periods);
        }
        else {
          $data[$reporting_period->id] = $values[$reporting_period->id];
        }
      }
      else {
        // Caseloads.
        $data[$reporting_period->id] = $attachment->getMeasurementMetricValue($data_point, $reporting_period->id);
      }
      $totals = $attachment->values;

      // Prepare the tooltip items.
      $tooltip_items = [];
      $tooltip_format = (string) $this->t('Monitoring period #@period_number<br />@date_range');

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
   *
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment|null
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof DataAttachment ? $attachment : NULL;
  }

}
