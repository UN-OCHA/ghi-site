<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form class for the HPC API configuration form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hpc_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $config = $this->config('hpc_api.settings');

    $form['url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('URL'),
      '#description' => $this->t('The URL to the HPC API.'),
      '#default_value' => $config->get('url'),
      '#required' => TRUE,
    ];

    $form['default_api_version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default API version'),
      '#description' => $this->t('The API version to use by default.'),
      '#default_value' => $config->get('default_api_version'),
      '#required' => TRUE,
    ];

    $form['auth_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('User name'),
      '#description' => $this->t('The username to authenticate public requests to the HPC API.'),
      '#default_value' => $config->get('auth_username'),
      '#required' => TRUE,
    ];

    $form['auth_password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('The password to authenticate public requests to the HPC API.'),
      '#default_value' => $config->get('auth_password'),
      '#required' => TRUE,
    ];

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('An API key for backend requests to the HPC API.'),
      '#default_value' => $config->get('api_key'),
      '#required' => TRUE,
    ];

    $form['public_base_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Public base path'),
      '#description' => $this->t('The base path for public endpoints of the HPC API.'),
      '#default_value' => $config->get('public_base_path'),
      '#required' => TRUE,
    ];

    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Timeout'),
      '#description' => $this->t('The global timeout in seconds for requests to the HPC API.'),
      '#default_value' => $config->get('timeout'),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache lifetime'),
      '#description' => $this->t('The maximum time in seconds that data from the HPC API should be kept in local cache.'),
      '#default_value' => $config->get('cache_lifetime'),
      '#min' => 1,
      '#step' => 1,
      '#required' => TRUE,
    ];

    $form['use_gzip_compression'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use gzip compression'),
      '#description' => $this->t('Check this if you want that all API requests use GZIP compression if available.'),
      '#default_value' => $config->get('use_gzip_compression'),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hpc_api.settings');
    $config->set('url', $form_state->getValue('url'));
    $config->set('default_api_version', $form_state->getValue('default_api_version'));
    $config->set('auth_username', $form_state->getValue('auth_username'));
    $config->set('auth_password', $form_state->getValue('auth_password'));
    $config->set('api_key', $form_state->getValue('api_key'));
    $config->set('public_base_path', $form_state->getValue('public_base_path'));
    $config->set('timeout', $form_state->getValue('timeout'));
    $config->set('cache_lifetime', $form_state->getValue('cache_lifetime'));
    $config->set('use_gzip_compression', $form_state->getValue('use_gzip_compression'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hpc_api.settings',
    ];
  }

}
