<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\hpc_common\Helpers\ThemeHelper;
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
      'hpc_common.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('ghi_blocks.table_settings');
    $display_style = $this->config('hpc_common.settings')->get('numeric_display_style');
    $display_style_options = ThemeHelper::getDisplayStyleOptions();
    $form['numeric_display_style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Numeric display style'),
      '#description' => $this->t('Controls numeric formatting in custom page element tables.'),
      '#options' => $display_style_options,
      '#default_value' => array_key_exists($display_style, $display_style_options) ? $display_style : ThemeHelper::DISPLAY_STYLE_DEFAULT,
    ];
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
    $display_style = $form_state->getValue('numeric_display_style');
    $display_style_options = ThemeHelper::getDisplayStyleOptions();
    if (!array_key_exists($display_style, $display_style_options)) {
      $display_style = ThemeHelper::DISPLAY_STYLE_DEFAULT;
    }
    $this->config('hpc_common.settings')
      ->set('numeric_display_style', $display_style)
      ->save();

    $config = $this->config('ghi_blocks.table_settings');
    $config->set('scroll_indicator', $form_state->getValue('scroll_indicator'));
    $config->save();
    $this->cacheDiscoveryBackend->invalidateAll();
    $this->cacheRenderBackend->invalidateAll();
    return parent::submitForm($form, $form_state);
  }

}
