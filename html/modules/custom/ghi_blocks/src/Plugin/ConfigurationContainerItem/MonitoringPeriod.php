<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;
use Drupal\hpc_api\Query\EndpointQueryManager;

/**
 * Provides an attachment data item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "monitoring_period",
 *   label = @Translation("Monitoring period"),
 *   description = @Translation("This item displays the monitoring period for an attachment."),
 * )
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
class MonitoringPeriod extends ConfigurationContainerItemPluginBase {

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
    $period = $this->getAttachmentObject()->monitoring_period;
    return $period ? $period->periodNumber : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    return DataPointHelper::formatMonitoringPeriod($attachment, $this->get('display_type'));
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
   * Get the current attachment object.
   *
   * @return object
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment;
  }

}
