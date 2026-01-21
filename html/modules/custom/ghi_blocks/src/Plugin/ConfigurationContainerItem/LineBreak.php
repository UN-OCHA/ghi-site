<?php

namespace Drupal\ghi_blocks\Plugin\ConfigurationContainerItem;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_form_elements\Attribute\ConfigurationContainerItem;
use Drupal\ghi_form_elements\ConfigurationContainerItemPluginBase;

/**
 * Provides a line break item for configuration containers.
 */
#[ConfigurationContainerItem(
  id: 'line_break',
  label: new TranslatableMarkup('Line break'),
  description: new TranslatableMarkup("This item doesn't display anything but forces a line break."),
)]
class LineBreak extends ConfigurationContainerItemPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm($element, FormStateInterface $form_state) {
    self::setElementParents($element);
    $element['label']['#access'] = FALSE;
    return $element;
  }

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
