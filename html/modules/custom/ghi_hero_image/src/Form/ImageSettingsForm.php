<?php

namespace Drupal\ghi_hero_image\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Image settings form.
 */
class ImageSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_hero_image_image_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['ghi_hero_image.config'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('ghi_hero_image.config');
    $form['force_letterbox'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Letterbox hero images'),
      '#description' => $this->t('Check this to force hero images to show in letterbox format.'),
      '#default_value' => $config->get('force_letterbox'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_hero_image.config');
    $config->set('force_letterbox', $form_state->getValue('force_letterbox'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

}
