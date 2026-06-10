<?php

namespace Drupal\ghi_plans\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Legacy project settings form.
 */
class LegacyProjectSettingsForm extends ConfigFormBase {

  /**
   * The legacy project config name.
   */
  private const CONFIG_NAME = 'ghi_plans.legacy_projects';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_plans_legacy_project_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      self::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config(self::CONFIG_NAME);
    $form['intro'] = [
      '#markup' => '<p>' . $this->t('These settings expect URLs for a legacy project export published with GitHub Pages. The base URL is used to load project HTML files and assets, while the optional tree URL can point to the corresponding GitHub repository tree API.') . '</p>',
    ];
    $form['base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Legacy project base URL'),
      '#description' => $this->t('Base URL for the published legacy project files. The renderer appends /projects/{project_id}.html to this value.'),
      '#default_value' => $config->get('base_url'),
      '#maxlength' => 2048,
    ];
    $form['tree_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Legacy project tree URL'),
      '#description' => $this->t('URL for a JSON tree index used to check which legacy project files exist. Leave this empty to render legacy project links without checking whether the target page exists.'),
      '#default_value' => $config->get('tree_url'),
      '#maxlength' => 2048,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    foreach (['base_url', 'tree_url'] as $key) {
      $value = trim((string) $form_state->getValue($key));
      if ($value !== '' && !UrlHelper::isValid($value, TRUE)) {
        $form_state->setErrorByName($key, $this->t('Enter a valid absolute URL.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);
    $config
      ->set('base_url', rtrim(trim((string) $form_state->getValue('base_url')), '/'))
      ->set('tree_url', rtrim(trim((string) $form_state->getValue('tree_url')), '/'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
