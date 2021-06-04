<?php

namespace Drupal\ghi_configuration_container\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_configuration_container\Traits\AjaxElementTrait;

/**
 * Provides a configuration container element.
 *
 * @FormElement("attachment_select")
 */
class AttachmentSelect extends FormElement {

  use AjaxElementTrait;

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#default_value' => NULL,
      '#input' => TRUE,
      '#tree' => TRUE,
      '#process' => [
        [$class, 'processAttachmentSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderAttachmentSelect'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#max_items' => NULL,
      '#preview' => NULL,
      '#plan_context' => NULL,

      '#tree' => TRUE,
      '#multiple' => FALSE,
      '#disabled' => FALSE,
      '#preview_only' => FALSE,
      '#show_filter' => FALSE,
      '#group_by_entity' => FALSE,
      '#include_available_map_metrics' => FALSE,
      '#disable_empty_locations' => FALSE,
      '#disabled' => FALSE,

      '#entity_types' => [],
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
   */
  public static function elementSubmit(array &$element, FormStateInterface $form_state, array $form) {
  }

  /**
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processConfigurationContainer(array &$element, FormStateInterface $form_state) {

    if (!empty($element['#available_options']['entities'])) {
      $entity_type_options = $element['#available_options']['entities'];
      if (empty($plan_context)) {
        $entity_type_options = array_merge(['overview' => t('Plan')], $entity_type_options);
      }
      // Either show a select with the available options for the entity type, or
      // set a preset value that should come from $element['#entity_type'].
      $element['entity_type'] = [
        '#type' => 'select',
        '#title' => t('Entity type'),
        '#options' => $entity_type_options,
        '#default_value' => $defaults['entity_type'],
        '#required' => TRUE,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $table_wrapper_id,
        ],
      ];

      if (!empty($element['#entity_type'])) {
        $defaults['entity_type'] = $element['#entity_type'];
        $element['entity_type']['#type'] = 'hidden';
        $element['entity_type']['#value'] = $defaults['entity_type'];
      }
    }

  }

  /**
   * Prerender callback.
   */
  public static function preRenderConfigurationContainer(array $element) {
    $element['#attributes']['type'] = 'attachment_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-attachment-select']);
    return $element;
  }

}
