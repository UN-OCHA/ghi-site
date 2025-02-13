<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Select;
use Drupal\ghi_content\Traits\RemoteElementTrait;

/**
 * Provides an select form element for remote sources.
 *
 * Usage example:
 * @code
 * $form['my_element'] = [
 *  '#type' => 'remote_source',
 *  '#default_value' => 'hpc_content_module',
 * ];
 * @endcode
 */
#[FormElement('remote_source')]
class RemoteSource extends Select {

  use RemoteElementTrait;

  /**
   * {@inheritDoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    $options = self::getRemoteSourceOptions();
    $disabled = count($options) <= 1;

    $options = self::getRemoteSourceOptions();

    $element['#options'] = $options;

    $element['#multiple'] = FALSE;
    $element['#description'] = $element['#description'] ?? '';

    if (empty($element['#default_value'])) {
      $element['#default_value'] = array_key_first($element['#options']);
    }

    if ($disabled) {
      $element['#disabled'] = TRUE;
      $element['#value'] = array_key_first($element['#options']);
      $element['#default_value'] = array_key_first($element['#options']);
      $element['#attributes']['disabled'] = 'disabled';
      $element['#description'] .= '<br />' . t('<em>Note:</em> This option is deactivated because there is only a single content source available: @content_source', [
        '@content_source' => reset($options),
      ]);
    }

    return parent::processSelect($element, $form_state, $complete_form);
  }

}
