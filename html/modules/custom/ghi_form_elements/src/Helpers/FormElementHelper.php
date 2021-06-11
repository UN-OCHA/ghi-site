<?php

namespace Drupal\ghi_form_elements\Helpers;

/**
 * Helper class for form elements.
 */
class FormElementHelper {

  /**
   * Get a selector to be used for states.
   *
   * @param array $element
   *   The base element.
   * @param array $subkeys
   *   Additional subkeys.
   *
   * @return string
   *   The final state selector string.
   */
  public static function getStateSelector(array $element, array $subkeys) {
    return reset($element['#parents']) . '[' . implode('][', array_merge(array_slice($element['#parents'], 1), $subkeys)) . ']';
  }

}
