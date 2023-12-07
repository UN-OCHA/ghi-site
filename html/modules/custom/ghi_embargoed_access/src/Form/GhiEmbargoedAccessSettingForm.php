<?php

namespace Drupal\ghi_embargoed_access\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a settings form for embargoed access.
 */
class GhiEmbargoedAccessSettingForm extends ConfigFormBase {

  /**
   * The embargoed access manager service.
   *
   * @var \Drupal\ghi_embargoed_access\EmbargoedAccessManager
   */
  protected $embargoedAccessManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var static $instance */
    $instance = parent::create($container);
    $instance->embargoedAccessManager = $container->get('ghi_embargoed_access.manager');
    return $instance;
  }

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

    // Find all currently protected nodes and mark them for re-index.
    $this->embargoedAccessManager->markAllForReindex();

    drupal_flush_all_caches();
    return parent::submitForm($form, $form_state);
  }

}
