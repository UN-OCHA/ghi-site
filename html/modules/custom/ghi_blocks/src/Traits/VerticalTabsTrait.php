<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Helper trait class to make working with vertical tabs easier.
 */
trait VerticalTabsTrait {

  /**
   * Find vertical tabs elements in the given form.
   *
   * @param array $form
   *   The form array.
   *
   * @return array
   *   An aray of first-level element keys.
   */
  private function findVerticalTabsElementKeys(array $form) {
    $element_keys = [];
    foreach (Element::children($form) as $child_key) {
      $child_element = &$form[$child_key];
      if (array_key_exists('#type', $child_element) && $child_element['#type'] == 'vertical_tabs') {
        $element_keys[] = $child_key;
      }
    }
    return $element_keys;
  }

  /**
   * Get the variable key used to store the current tab in the form storage.
   *
   * @param string $group
   *   The group key.
   *
   * @return string
   *   The variable key string.
   */
  private function getVariableKey($group) {
    return $group . '__active_tab';
  }

  /**
   * Process vertical tabs elements in the given form array.
   *
   * This processes only first-level elements. It sets the default tab and
   * sets a non-unique id.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function processVerticalTabs(array &$form, FormStateInterface $form_state) {
    // Add easier support for vertical tabs.
    foreach ($this->findVerticalTabsElementKeys($form) as $group) {
      $child_element = &$form[$group];

      $child_element['#parents'] = [$group];
      $child_element['#default_tab'] = $form_state->get($this->getVariableKey($group));

      foreach (Element::children($form) as $element_key) {
        if (!array_key_exists('#group', $form[$element_key])) {
          continue;
        }
        if ($form[$element_key]['#group'] != $group) {
          continue;
        }
        $form[$element_key]['#id'] = 'edit-' . $group . '-' . $element_key;
      }
    }
  }

  /**
   * Process vertical tabs submits.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function processVerticalTabsSubmit(array $form, FormStateInterface $form_state) {
    foreach ($this->findVerticalTabsElementKeys($form) as $group) {
      $variable_key = $this->getVariableKey($group);
      $value_key = [$group, $variable_key];
      if ($form_state->hasValue($value_key)) {
        // Set the active tab for this group.
        $form_state->set($variable_key, $form_state->getValue($value_key));
      }
    }
  }

}
