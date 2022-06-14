<?php

namespace Drupal\hpc_downloads\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Config form for HPC downloads.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'hpc_downloads_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $config = $this->config('hpc_downloads.settings');

    $form['logo_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path'),
      '#description' => $this->t('Enter an absolute path to the logo file that should be used for downloads. SVG files are not supported. If this is left empty, HPC Downloads will try to use the current theme logo which might not work properly.'),
      '#default_value' => $config->get('logo_path'),
    ];

    $form['logo_path_xls'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path (XLS)'),
      '#description' => $this->t('Enter an absolute path to the logo file that should be used for XLS downloads. SVG files are not supported. If this is left empty, HPC Downloads will try to use the current theme logo which might not work properly.'),
      '#default_value' => $config->get('logo_path_xls'),
    ];

    $form['download_lifetime'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Lifetime of generated downloads in minutes'),
      '#description' => $this->t('Specify the time that a generated download file will be kept before it is considered stale. Stale files are deleted during cron runs.'),
      '#default_value' => $config->get('download_lifetime'),
    ];

    $form['download_excel_row_segment_size'] = [
      '#type' => 'number',
      '#min' => 500,
      '#title' => $this->t('Segment size of Excel exports'),
      '#description' => $this->t('To account for memory and timeout issues, the Excel export data will be split into segments and be written sequentially. You can define the size of each segment here'),
      '#default_value' => $config->get('excel_segment_size'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('hpc_downloads.settings');
    $config->set('logo_path', $form_state->getValue('logo_path'));
    $config->set('logo_path_xls', $form_state->getValue('logo_path_xls'));
    $config->set('download_lifetime', $form_state->getValue('download_lifetime'));
    $config->set('excel_segment_size', $form_state->getValue('download_excel_row_segment_size'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'hpc_downloads.settings',
    ];
  }

}
