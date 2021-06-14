<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Helper trait for cluster restriction on configurtion item plugins.
 */
trait ValuePreviewConfigurationItemTrait {

  /**
   * Build the cluster restrict form element.
   *
   * @param array $default_value
   *   The default value for the element.
   *
   * @return array
   *   A form element array.
   */
  public function buildValuePreviewFormElement(array $default_value) {
    return [
      '#type' => 'item',
      '#title' => $this->t('Value preview'),
      '#markup' => $value,
      '#weight' => 50,
    ];
  }

  /**
   * Whether a preview should be displayed.
   *
   * @return bool
   *   TRUE if a preview should be displayed, FALSE otherwhise.
   */
  public function shouldDisplayPreview() {
    $plugin_configuration = $this->getPluginConfiguration();
    return !array_key_exists('value_preview', $plugin_configuration) ||$plugin_configuration['value_preview'] === TRUE;
  }

}
