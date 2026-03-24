<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Block settings form.
 */
class BlockSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_block_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_blocks.block_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('ghi_blocks.block_settings');
    $form['lazy_load'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Lazy load blocks'),
      '#description' => $this->t('Use lazy loading for all custom blocks.'),
      '#default_value' => $config->get('lazy_load'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_blocks.block_settings');
    $config->set('lazy_load', $form_state->getValue('lazy_load'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

}
