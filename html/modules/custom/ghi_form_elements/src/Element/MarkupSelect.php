<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides an element for selecting from rendered markup.
 */
#[FormElement('markup_select')]
class MarkupSelect extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => [],
      '#input' => TRUE,
      '#tree' => TRUE,
      '#options' => NULL,
      '#limit' => NULL,
      '#cols' => 5,
      '#process' => [
        [$class, 'processMarkupSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderMarkupSelect'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Element submit callback.
   *
   * @param array $element
   *   The base element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $form
   *   The full form.
   *
   * @todo Check if this is actually needed.
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
    $form_state->setRebuild(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input) {
      // Make sure input is returned as normal during item configuration.
      self::massageValues($input, ['selected']);
      $input = $input['selected'];
      return $input;
    }
    return NULL;
  }

  /**
   * Massage the submitted input values from strings to arrays.
   *
   * @param array $input
   *   The input array.
   * @param array $value_keys
   *   The value keys to process.
   */
  public static function massageValues(array &$input, array $value_keys) {
    foreach ($value_keys as $value_key) {
      if (!array_key_exists($value_key, $input) || empty($input[$value_key])) {
        $input[$value_key] = [];
        continue;
      }
      if (is_array($input[$value_key])) {
        continue;
      }
      if (strpos($input[$value_key], ',') === FALSE) {
        $input[$value_key] = (array) $input[$value_key];
        continue;
      }
      $input[$value_key] = array_filter(explode(',', $input[$value_key]));
    }
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processMarkupSelect(array &$element, FormStateInterface $form_state) {

    // Get a name that let's us identify this element.
    $name = Html::getUniqueId(implode('-', array_merge(['edit'], $element['#parents'])));

    $element['#wrapper_attributes']['data-drupal-selector'] = $name;

    $options = $element['#options'];

    $element['#attached']['library'][] = 'ghi_form_elements/markup_select';
    $element['#attached']['drupalSettings']['markup_select'][$name] = [
      'previews' => $options,
      'ids' => array_keys($options),
      'limit' => $element['#limit'],
      'cols' => $element['#cols'],
    ];
    $element['selected'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', array_filter((array) $element['#default_value'])),
      '#attributes' => ['class' => Html::getClass('items_selected')],
    ];

    if (!empty($element['#states'])) {
      // Propagate states logic to the child elements.
      $element['selected']['#states'] = $element['#states'];
      unset($element['#states']);
    }

    $form_state->addCleanValueKey(array_merge($element['#parents'], ['selected']));

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderMarkupSelect(array $element) {
    $element['#attributes']['type'] = 'markup_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-markup-select']);
    return $element;
  }

}
