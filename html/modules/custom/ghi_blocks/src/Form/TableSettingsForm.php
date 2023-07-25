<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Table settings form.
 */
class TableSettingsForm extends ConfigFormBase {

  /**
   * The discovery cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheDiscoveryBackend;

  /**
   * The render cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheRenderBackend;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\ghi_blocks\Form\TableSettingsForm $instance */
    $instance = parent::create($container);
    $instance->cacheDiscoveryBackend = $container->get('cache.discovery');
    $instance->cacheRenderBackend = $container->get('cache.render');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_table_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_blocks.table_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('ghi_blocks.table_settings');
    $form['scroll_indicator'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use scroll indicator'),
      '#description' => $this->t('Use a javascript-based scroll indicator for larger tables.'),
      '#default_value' => $config->get('scroll_indicator'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('ghi_blocks.table_settings');
    $config->set('scroll_indicator', $form_state->getValue('scroll_indicator'));
    $config->save();
    $this->cacheDiscoveryBackend->invalidateAll();
    $this->cacheRenderBackend->invalidateAll();
    return parent::submitForm($form, $form_state);
  }

}
