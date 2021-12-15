<?php

namespace Drupal\ghi_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides an edit form for remote sources processors.
 */
class RemoteSourceEditForm extends FormBase {

  /**
   * The remote source plugin to edit.
   *
   * @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   */
  protected $remoteSource;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'remote_source_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, RemoteSourceInterface $remote_source = NULL) {
    $this->remoteSource = $remote_source;

    $form['settings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['settings'] += $this->remoteSource->buildConfigurationForm($form, $form_state);

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
    $form_state->cleanValues();
    $this->remoteSource->setConfiguration($form_state->getValue('settings'));
    $this->remoteSource->saveConfiguration();

    $this->messenger()->addMessage($this->t('The settings for <em>@remote_source</em> have been saved.', [
      '@remote_source' => $this->remoteSource->getPluginLabel(),
    ]));
    $form_state->setRedirectUrl(Url::fromRoute('ghi_content.remote.plugin_list'));
  }

}
