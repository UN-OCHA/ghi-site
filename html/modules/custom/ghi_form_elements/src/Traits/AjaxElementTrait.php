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
    $prefix = '';
    if (array_key_exists('#array_parents', $element)) {
      $prefix = implode('-', $element['#array_parents']);
    }
    elseif (array_key_exists('#attributes', $element) && array_key_exists('class', $element['#attributes'])) {
      $prefix = reset($element['#attributes']['class']);
    }
    elseif (method_exists(self::class, 'getFormId')) {
      $prefix = Html::getClass(self::getFormId());
    }
    return $prefix . '-wrapper';
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
    self::$elementParentsFormKey = array_key_exists('#array_parents', $element) ? $element['#array_parents'] : [];
  }

  /**
   * Prepare a form based on FormBase.
   */
  public static function prepareAjaxForm(&$form, FormStateInterface $form_state) {
    self::setElementParents($form);
    self::setClassOnAjaxElements($form);
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

    return $response;
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

  /**
   * Recursively hide the elements and all elements contained.
   *
   * @param array $element
   *   The root element array.
   */
  private static function hideAllElements(array &$element) {
    $class_name = Html::getClass('visually-hidden');

    // Hide the title.
    $element['#title_display'] = 'invisible';

    // Visually hide the element itself.
    $class_parents = ['#attributes', 'class'];
    $classes = NestedArray::keyExists($element, $class_parents) ? NestedArray::getValue($element, $class_parents) : [];
    if (!in_array($class_name, $classes)) {
      $classes[] = $class_name;
      NestedArray::setValue($element, $class_parents, $classes, TRUE);
    }
    foreach (Element::children($element) as $element_key) {
      self::hideAllElements($element[$element_key]);
    }
  }

  /**
   * Provide an "action" by looking at the triggering element array parents.
   */
  public static function getActionFromFormState(FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!$triggering_element || empty($triggering_element['#array_parents'])) {
      return NULL;
    }
    return end($triggering_element['#array_parents']);
  }

}
