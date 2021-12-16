<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Query\AttachmentQuery;

/**
 * Provides an attachment data item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "monitoring_period",
 *   label = @Translation("Monitoring period"),
 *   description = @Translation("This item displays the monitoring period for an attachment."),
 * )
 */
class MonitoringPeriod extends ConfigurationContainerItemPluginBase {

  /**
   * The attachment query.
   *
   * @var \Drupal\ghi_plans\Query\AttachmentQuery
   */
  public $attachmentQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AttachmentQuery $attachment_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->attachmentQuery = $attachment_query;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ghi_plans.attachment_query'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['display_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Display type'),
      '#default_value' => $this->getSubmittedValue($element, $form_state, 'display_type', 'text'),
      '#options' => [
        'text' => $this->t('Display as text'),
        'icon' => $this->t('Display as icon with tooltip'),
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $period = $this->getMonitoringPeriod();
    return $period->periodNumber;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $period = $this->getMonitoringPeriod();
    $display_type = $this->get('display_type');
    if ($display_type == 'icon') {
      return [
        '#theme' => 'hpc_tooltip',
        '#tooltip' => [
          '#theme' => 'hpc_reporting_period',
          '#reporting_period' => $period,
        ],
        '#class' => 'api-url',
        '#tag_content' => [
          '#theme' => 'hpc_icon',
          '#icon' => 'calendar_today',
          '#tag' => 'span',
        ],
      ];
    }
    else {
      return [
        '#theme' => 'hpc_reporting_period',
        '#reporting_period' => $period,
      ];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getClasses() {
    $classes = parent::getClasses();
    $classes[] = Html::getClass($this->getPluginId() . '--' . $this->get('display_type'));
    return $classes;
  }

  /**
   * Get the monitoring period for this item.
   *
   * @todo Correct this. Currently it just takes one period from the periods
   * imported on plan level. Instead we need to check for a measurement or an
   * attachment in the current context and extract it from there.
   */
  private function getMonitoringPeriod() {
    $plan_node = $this->getContextValue('plan_object');
    $reporting_periods = unserialize($plan_node->field_plan_reporting_periods->value);
    if (empty($reporting_periods)) {
      return NULL;
    }
    $reporting_period = end($reporting_periods);
    return $reporting_period;
  }

}
