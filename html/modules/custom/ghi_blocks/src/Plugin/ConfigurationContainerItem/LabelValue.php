<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;
use Drupal\ghi_plans\Helpers\DataPointHelper;

/**
 * Provides an entity counter item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "label_value",
 *   label = @Translation("Label/value"),
 *   description = @Translation("This item displays an arbitrary label/value pair."),
 * )
 */
class LabelValue extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);

    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => array_key_exists('value', $this->config) ? $this->config['value'] : NULL,
    ];
    $formatting_options = DataPointHelper::getFormattingOptions();
    unset($formatting_options['auto']);
    $element['formatting'] = [
      '#type' => 'select',
      '#title' => $this->t('Formatting'),
      '#options' => $formatting_options,
      '#default_value' => array_key_exists('formatting', $this->config) ? $this->config['formatting'] : NULL,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    $value = $this->getValue();
    $formatting = $this->config['formatting'] ?? 'raw';
    $decimal_format = NULL;
    switch ($formatting) {
      case 'raw':
        $rendered_value = [
          '#markup' => $value,
        ];
        break;

      case 'currency':
        $rendered_value = [
          '#theme' => 'hpc_currency',
          '#value' => $value,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#scale' => 'full',
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'amount_rounded':
        $rendered_value = [
          '#theme' => 'hpc_amount',
          '#amount' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;

      case 'percent':
        $rendered_value = [
          '#theme' => 'hpc_percent',
          '#ratio' => $value,
          '#decimals' => 1,
          '#decimal_format' => $decimal_format,
        ];
        break;
    }

    return $rendered_value;
  }

}
