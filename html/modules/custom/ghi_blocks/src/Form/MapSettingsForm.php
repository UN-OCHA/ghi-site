<?php

namespace Drupal\ghi_blocks\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Map settings form.
 */
class MapSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ghi_blocks_map_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'ghi_blocks.map_settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $map_config = $this->config('ghi_blocks.map_settings');
    $form['mapbox_proxy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Proxy requests to mapbox.com'),
      '#description' => $this->t('Use a local proxy when retrieving map tiles from mapbox. This allows for caching and thus limiting the amount of requests to mapbox.com.'),
      '#default_value' => $map_config->get('mapbox_proxy'),
    ];
    $form['country_outlines'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show country outlines'),
      '#description' => $this->t('Show country outlines on the overview maps. This uses GeoJson data from the HPC API as available. If not available, it falls back to non-un-approved data from <a href="https://github.com/datasets/geo-countries">Natural Earth</a>.'),
      '#default_value' => $map_config->get('country_outlines'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $map_config = $this->config('ghi_blocks.map_settings');
    $map_config->set('mapbox_proxy', $form_state->getValue('mapbox_proxy'));
    $map_config->set('country_outlines', $form_state->getValue('country_outlines'));
    $map_config->save();
    return parent::submitForm($form, $form_state);
  }

}
