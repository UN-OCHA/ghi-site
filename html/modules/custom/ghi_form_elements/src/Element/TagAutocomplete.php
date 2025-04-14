<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;

/**
 * Provides an element for tag selection.
 */
#[FormElement('tag_autocomplete')]
class TagAutocomplete extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#tags' => NULL,
      '#default_value' => NULL,
      '#disabled_tags' => FALSE,
      '#preview_summary' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processTagAutocomplete'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderTagAutocomplete'],
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
  public static function processTagAutocomplete(array &$element, FormStateInterface $form_state) {
    // Get a name that let's us identify this element.
    $name = Html::getUniqueId(implode('-', array_merge(['edit'], $element['#parents'])));
    $default_tag_ids = !empty($element['#default_value']['tag_ids']) ? array_map(function ($item) {
      return $item['target_id'];
    }, $element['#default_value']['tag_ids']) : NULL;
    $default_tags = $default_tag_ids ? \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadMultiple($default_tag_ids) : [];

    $element['#wrapper_attributes']['data-drupal-selector'] = $name;
    $element['tag_ids'] = [
      '#type' => 'entity_autocomplete_active_tags',
      '#title' => t('Tags'),
      '#description' => t('Select the tags associated with this section. This controls the content that will be available.'),
      '#target_type' => 'taxonomy_term',
      '#selection_handler' => 'default',
      '#selection_settings' => [
        'target_bundles' => ['tags'],
      ],
      '#autocreate' => [
        'bundle' => 'tags',
        'uid' => \Drupal::currentUser()->id(),
      ],
      '#tags' => TRUE,
      '#default_value' => $default_tags,
      '#attached' => [
        'library' => ['ghi_form_elements/active_tags'],
      ],
      '#element_validate' => ['ghi_form_elements_entity_autocomplete_active_tags_element_validate'],
      '#maxlength' => NULL,
    ];
    unset($element['#title']);

    if (!empty($element['#disabled_tags'])) {
      foreach ($element['#disabled_tags'] as $id) {
        $element['tag_ids'][$id]['#disabled'] = TRUE;
      }
    }

    $element['tag_op'] = [
      '#type' => 'checkbox',
      '#title' => t('Require all selected tags'),
      '#return_value' => 'AND',
      '#wrapper_attributes' => ['data-drupal-selector' => 'tag_op'],
      '#default_value' => $element['#default_value']['tag_op'] ?? FALSE,
    ];

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderTagAutocomplete(array $element) {
    $element['#attributes']['type'] = 'tag_autocomplete';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-tag-autocomplete']);
    return $element;
  }

}
