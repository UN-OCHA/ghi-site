<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Logframe settings form.
 */
class LogframeSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_logframe_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_blocks.logframe_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $logframe_config = $this->config('ghi_blocks.logframe_settings');
    $form['lazy_load'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lazy load logframe elements'),
      '#description' => $this->t('Use lazy loading for attachment tables on logframe elements.'),
      '#default_value' => $logframe_config->get('lazy_load'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $logframe_config = $this->config('ghi_blocks.logframe_settings');
    $logframe_config->set('lazy_load', $form_state->getValue('lazy_load'));
    $logframe_config->save();
    return parent::submitForm($form, $form_state);
  }

}
