<?php

namespace Drupal\hpc_api\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form class for the HPC API configuration form.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * Default values for settings that remain editable on this form.
   */
  private const DEFAULT_VALUES = [
    'connect_timeout' => 3,
    'timeout' => 25,
    'flow_custom_search_timeout' => 6,
    'cache_lifetime' => 3600,
    'use_gzip_compression' => FALSE,
    'log_api_errors' => TRUE,
  ];

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

    $form['connect_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Connect timeout'),
      '#description' => $this->t('The maximum time in seconds for opening connections to the HPC API.'),
      '#default_value' => $this->getConfigValue($config->get('connect_timeout'), 'connect_timeout'),
      '#min' => 1,
      '#step' => 1,
      '#required' => FALSE,
    ];

    $form['timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Total timeout'),
      '#description' => $this->t('The maximum total time in seconds for requests to the HPC API.'),
      '#default_value' => $this->getConfigValue($config->get('timeout'), 'timeout'),
      '#min' => 1,
      '#step' => 1,
      '#required' => FALSE,
    ];

    $form['flow_custom_search_timeout'] = [
      '#type' => 'number',
      '#title' => $this->t('Custom search timeout'),
      '#description' => $this->t('The maximum total time in seconds for legacy custom search requests to the HPC API.'),
      '#default_value' => $this->getConfigValue(
        $config->get('flow_custom_search_timeout'),
        'flow_custom_search_timeout'
      ),
      '#min' => 1,
      '#step' => 1,
      '#required' => FALSE,
    ];

    $form['cache_lifetime'] = [
      '#type' => 'number',
      '#title' => $this->t('Cache lifetime'),
      '#description' => $this->t('The maximum time in seconds that data from the HPC API should be kept in local cache.'),
      '#default_value' => $this->getConfigValue($config->get('cache_lifetime'), 'cache_lifetime'),
      '#min' => 1,
      '#step' => 1,
      '#required' => FALSE,
    ];

    $form['use_gzip_compression'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use gzip compression'),
      '#description' => $this->t('Check this if you want that all API requests use GZIP compression if available.'),
      '#default_value' => $this->getConfigValue($config->get('use_gzip_compression'), 'use_gzip_compression'),
      '#required' => FALSE,
    ];

    $form['log_api_errors'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log API errors'),
      '#description' => $this->t('Check this if you want that errors returned from the API are logged.'),
      '#default_value' => $this->getConfigValue($config->get('log_api_errors'), 'log_api_errors'),
      '#required' => FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hpc_api.settings');
    foreach (['connect_timeout', 'timeout', 'flow_custom_search_timeout', 'cache_lifetime'] as $key) {
      $config->set($key, (int) $this->getConfigValue($form_state->getValue($key), $key));
    }
    foreach (['use_gzip_compression', 'log_api_errors'] as $key) {
      $config->set($key, (bool) $this->getConfigValue($form_state->getValue($key), $key));
    }
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * Get a submitted or stored setting, falling back to the module default.
   *
   * @param mixed $value
   *   The submitted or stored value.
   * @param string $key
   *   The config key.
   *
   * @return mixed
   *   The normalized value.
   */
  private function getConfigValue($value, string $key) {
    return $value === NULL || $value === '' ? self::DEFAULT_VALUES[$key] : $value;
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
