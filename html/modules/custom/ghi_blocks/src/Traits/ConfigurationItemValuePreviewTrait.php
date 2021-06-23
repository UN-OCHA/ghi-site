<?php

namespace Drupal\ghi_blocks\Traits;

/**
 * Helper trait for cluster restriction on configurtion item plugins.
 */
trait ConfigurationItemValuePreviewTrait {

  /**
   * Build the cluster restrict form element.
   *
   * @param mixed $default_value
   *   The default value for the element. Either a render array or a plain
   *   value.
   *
   * @return array
   *   A form element array.
   */
  public function buildValuePreviewFormElement($default_value) {
    $build = [
      '#type' => 'item',
      '#title' => $this->t('Value preview'),
      '#weight' => 50,
    ];
    if (is_array($default_value)) {
      $build[] = $default_value;
    }
    else {
      $build['#markup'] = $default_value;
    }
    return $build;
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
