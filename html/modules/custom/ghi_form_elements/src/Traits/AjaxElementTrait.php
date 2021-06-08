<?php

namespace Drupal\ghi_form_elements\Traits;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * Helper trait for ajax support on form elements.
 */
trait AjaxElementTrait {

  /**
   * Storage for an element parents array.
   *
   * @var array
   */
  protected static $elementParentsFormKey;

  /**
   * Get a wrapper ID for an element.
   *
   * @param array $element
   *   The form element.
   *
   * @return string
   *   The wrapper id.
   */
  public static function getWrapperId(array $element) {
    return implode('-', $element['#array_parents']) . '-wrapper';
  }

  /**
   * Set the elements parents of the given element.
   *
   * @param array $element
   *   The element for which to store the array parents.
   */
  protected static function setElementParents(array $element) {
    // Put the root path to this element into the form storage, to have it
    // easily available to update the full element after an ajax action.
    self::$elementParentsFormKey = $element['#array_parents'];
  }

  /**
   * Assuming inheritance from Drupal\Core\Render\Element\RenderElement.
   *
   * @see RenderElement::processAjaxForm
   */
  public static function processAjaxForm(&$element, FormStateInterface $form_state, &$complete_form) {
    self::setElementParents($element);
    self::setClassOnAjaxElements($element);
    return parent::processAjaxForm($element, $form_state, $complete_form);
  }

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
    // Just update the full element.
    $triggering_element = $form_state->getTriggeringElement();
    $wrapper_id = $triggering_element['#ajax']['wrapper'];
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#' . $wrapper_id, NestedArray::getValue($form, self::$elementParentsFormKey)));

    // If a submit button has been triggered and we have a preview container,
    // update that too.
    if ($triggering_element['#type'] == 'submit' && $preview_container = self::findPreviewContainer($form)) {
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

  /**
   * Recursively set a class on ajax enabled elements.
   *
   * @param array $element
   *   The root element array.
   */
  private static function setClassOnAjaxElements(array &$element) {
    $class_name = Html::getClass('ajax-enabled');

    $class_parents = ['#attributes', 'class'];
    $classes = NestedArray::keyExists($element, $class_parents) ? NestedArray::getValue($element, $class_parents) : [];
    if (array_key_exists('#ajax', $element) && !in_array($class_name, $classes)) {
      $classes[] = $class_name;
      NestedArray::setValue($element, $class_parents, $classes);
    }
    foreach (Element::children($element) as $element_key) {
      self::setClassOnAjaxElements($element[$element_key]);
    }
  }

}
