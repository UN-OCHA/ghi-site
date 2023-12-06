<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;

/**
 * Provides a line break item for configuration containers.
 *
 * @ConfigurationContainerItem(
 *   id = "line_break",
 *   label = @Translation("Line break"),
 *   description = @Translation("This item doesn't display anything but forces a line break."),
 * )
 */
class LineBreak extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getRenderArray() {
    return [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#wrapper_attributes' => [
        'class' => 'line-break',
      ],
    ];
  }

}
