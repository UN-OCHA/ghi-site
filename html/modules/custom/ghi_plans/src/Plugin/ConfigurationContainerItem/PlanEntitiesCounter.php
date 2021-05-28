<?php

namespace Drupal\ghi_plans\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_configuration_container\ConfigurationContainerItemPluginBase;

/**
 * Provides a configuration container element.
 *
 * @ConfigurationContainerItem(
 *   id = "plan_entities_counter",
 *   label = @Translation("Plan entities counter"),
 * )
 */
class PlanEntitiesCounter extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    $element = parent::buildForm($element, $form_state);
    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Value'),
      '#default_value' => $this->getValue(),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return $this->config['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->config['value'];
  }

}
