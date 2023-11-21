<?php

namespace Drupal\ghi_embargoed_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings form for embargoed access.
 */
class GhiEmbargoedAccessSettingForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ghi_embargoed_access.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_embargoed_access_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('ghi_embargoed_access.settings');
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Global protection enabled'),
      '#description' => $this->t('Check to use the global protection for embargoed access.'),
      '#default_value' => $config->get('enabled'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_embargoed_access.settings');
    $config->set('enabled', $form_state->getValue('enabled'));
    $config->save();
    drupal_flush_all_caches();
    return parent::submitForm($form, $form_state);
  }

}
