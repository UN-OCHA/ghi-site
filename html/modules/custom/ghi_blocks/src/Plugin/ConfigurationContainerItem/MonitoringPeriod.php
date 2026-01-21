<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment;

/**
 * Provides a monitoring period item for configuration containers.
 *
 * @phpcs:disable DrupalPractice.FunctionCalls.InsecureUnserialize
 */
#[ConfigurationContainerItem(
  id: 'monitoring_period',
  label: new TranslatableMarkup('Monitoring period'),
  description: new TranslatableMarkup('This item displays the monitoring period for an attachment.'),
)]
class MonitoringPeriod extends ConfigurationContainerItemPluginBase {

  const ITEM_TYPE = 'monitoring_period';

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
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }
    $period = $attachment->getCurrentMonitoringPeriod();
    return $period ? $period->getPeriodNumber() : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $attachment = $this->getAttachmentObject();
    if (!$attachment) {
      return NULL;
    }
    return $attachment->formatMonitoringPeriod($this->get('display_type'));
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
   * @return \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment|null
   *   The attachment object.
   */
  private function getAttachmentObject() {
    $attachment = $this->getContextValue('attachment');
    return $attachment instanceof DataAttachment ? $attachment : NULL;
  }

}
