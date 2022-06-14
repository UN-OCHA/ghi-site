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
    return self::getStateSelectorFromParents($element['#parents'], $subkeys);
  }

  /**
   * Get a selector from the passed in parents array.
   *
   * @param array $parents
   *   The parents array.
   * @param array $subkeys
   *   Additional subkeys.
   *
   * @return string
   *   The final state selector string.
   */
  public static function getStateSelectorFromParents(array $parents, array $subkeys) {
    return reset($parents) . '[' . implode('][', array_merge(array_slice($parents, 1), $subkeys)) . ']';
  }

}
