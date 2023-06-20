<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides an element for tag selection.
 *
 * @FormElement("tag_selection")
 */
class TagSelect extends FormElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#tags' => NULL,
      '#preview_summary' => FALSE,
      '#default_value' => NULL,
      '#disabled_tags' => FALSE,
      '#preview_summary' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processTagSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderTagSelect'],
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
    if (is_array($input)) {
      // Make sure input is returned as normal during item configuration.
      if (!array_key_exists('tag_op', $input) || $input['tag_op'] === NULL) {
        $input['tag_op'] = 'OR';
      }
      $input['tag_content_selected'] = array_filter(!is_array($input['tag_content_selected']) ? explode(',', $input['tag_content_selected']) : $input['tag_content_selected']);
      return $input;
    }
    return NULL;
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processTagSelect(array &$element, FormStateInterface $form_state) {

    // Get a name that let's us identify this element.
    $name = Html::getUniqueId(implode('-', array_merge(['edit'], $element['#parents'])));

    $element['#wrapper_attributes']['data-drupal-selector'] = $name;
    $element['tag_ids'] = [
      '#type' => 'checkboxes',
      '#title' => $element['#title'],
      '#options' => $element['#tags'],
      '#default_value' => $element['#default_value']['tag_ids'] ?? [],
    ];
    unset($element['#title']);

    if (!empty($element['#disabled_tags'])) {
      foreach ($element['#disabled_tags'] as $id) {
        $element['tag_ids'][$id]['#disabled'] = TRUE;
      }
    }

    if (!empty($element['#preview_summary']) && !empty($element['#preview_summary']['ids_by_tag'])) {
      $element['#attached']['library'][] = 'ghi_form_elements/tag_select.preview';
      $element['#attached']['drupalSettings']['tag_select'][$name] = $element['#preview_summary'];
    }

    $element['tag_op'] = [
      '#type' => 'checkbox',
      '#title' => t('Require all selected tags'),
      '#return_value' => 'AND',
      '#wrapper_attributes' => ['data-drupal-selector' => 'tag_op'],
      '#default_value' => $element['#default_value']['tag_op'] ?? FALSE,
    ];

    $element['tag_content_selected'] = [
      '#type' => 'hidden',
      '#default_value' => implode(',', $element['#default_value']['tag_content_selected'] ?? []),
      '#attributes' => ['class' => Html::getClass('selected_items')],
    ];

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderTagSelect(array $element) {
    $element['#attributes']['type'] = 'tag_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-tag-select']);
    return $element;
  }

}
