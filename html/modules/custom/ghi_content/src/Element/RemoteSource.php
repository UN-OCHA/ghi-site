<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Select;

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
 *
 * @FormElement("remote_source")
 */
class RemoteSource extends Select {

  /**
   * {@inheritDoc}
   */
  public static function processSelect(&$element, FormStateInterface $form_state, &$complete_form) {
    $remote_source_manager = self::getRemoteSourceManager();
    $definitions = $remote_source_manager->getDefinitions();
    $disabled = count($definitions) <= 1;

    $element['#options'] = array_map(function ($item) {
      return $item['label'];
    }, $definitions);

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
        '@content_source' => $definitions[array_key_first($definitions)]['label'],
      ]);
    }

    return parent::processSelect($element, $form_state, $complete_form);
  }

  /**
   * Get the remote source manager.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceManager
   *   The remote source manager.
   */
  private static function getRemoteSourceManager() {
    return \Drupal::service('plugin.manager.remote_source');
  }

}
