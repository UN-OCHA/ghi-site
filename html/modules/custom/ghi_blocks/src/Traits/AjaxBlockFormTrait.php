<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Helper trait for ajax form support on block plugins.
 *
 * If the method updateAjax is used as an ajax callback, the element must also
 * specify wrapper and array_parents:
 * @code
 * $form['id_type']['#ajax'] = [
 *   'event' => 'change',
 *   'callback' => [$this, 'updateAjax'],
 *   'wrapper' => $wrapper_id,
 *   'array_parents' => array_merge($form['#array_parents'], ['entity_ids']),
 * ];
 * $form['id_type'] = [
 *   ...
 * ];
 * @endcode
 *
 * Note that this callback also checks if a preview container is present
 * somewhere in the form, in which case that would be automatically updated
 * too. If this is not intended, the element can set the block_preview_update
 * property to FALSE.
 */
trait AjaxBlockFormTrait {

  /**
   * Generic ajax callback.
   *
   * @param array $form
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state interface.
   *
   * @return array
   *   The part of the form structure that should be replaced.
   */
  public static function updateAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $triggering_element = $form_state->getTriggeringElement();
    $ajax = $triggering_element['#ajax'];

    if (!empty($ajax['wrapper']) && !empty($ajax['array_parents'])) {
      $wrapper_id = $ajax['wrapper'];
      $parents = $ajax['array_parents'];
      // Just update the full element.
      $response->addCommand(new ReplaceCommand('#' . $wrapper_id, NestedArray::getValue($form, $parents)));
    }

    // If a submit button has been triggered and we have a preview container,
    // update that too.
    $block_preview_update = !array_key_exists('block_preview_update', $ajax) || $ajax['block_preview_update'];
    if ($block_preview_update && $preview_container = self::findPreviewContainer($form)) {
      $response->addCommand(new ReplaceCommand('#' . $preview_container['update_preview']['#ajax']['wrapper'], $preview_container));
    }

    return $response;
  }

  /**
   * Find a preview container.
   *
   * @param array $form
   *   The form or element array.
   *
   * @return array|null
   *   The preview container element or NULL.
   */
  private static function findPreviewContainer(array $form) {
    if (array_key_exists('preview_container', $form)) {
      return $form['preview_container'];
    }
    foreach (Element::children($form) as $element_key) {
      $preview_container = self::findPreviewContainer($form[$element_key]);
      if ($preview_container) {
        return $preview_container;
      }
    }
    return NULL;
  }

}
