<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Provides an attachment select element.
 *
 * @FormElement("entity_attachment_select")
 */
class EntityAttachmentSelect extends FormElement {

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
        [$class, 'processEntityAttachmentSelect'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderEntityAttachmentSelect'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#disabled' => FALSE,
      '#entity_types' => [],
      '#entity_type' => NULL,
      '#attachment_options' => NULL,
      '#attachment_type' => NULL,
      '#element_context' => [],
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
    if ($input !== NULL) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    return NULL;
  }

  /**
   * Process the entity attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processEntityAttachmentSelect(array &$element, FormStateInterface $form_state) {
    $element['#attached']['library'][] = 'ghi_form_elements/entity_attachment_select';

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $values = NestedArray::mergeDeepArray([
      (array) $element['#default_value'],
      (array) $form_state->getValue($element['#parents']),
    ], TRUE);
    $defaults = [
      'entities' => [
        'entity_ids' => array_filter($values['entities']['entity_ids'] ?? []),
      ],
      'attachments' => [
        'filter' => [
          'entity_type' => $values['attachments']['filter']['entity_type'] ?? NULL,
          'attachment_type' => $values['attachments']['filter']['attachment_type'] ?? NULL,
          'attachment_prototype' => $values['attachments']['filter']['attachment_prototype'] ?? NULL,
        ],
        'attachment_id' => array_filter($values['attachments']['attachment_id'] ?? []),
      ],
    ];

    $form_state->set('entities', $defaults['entities']);
    $triggering_element = $form_state->getTriggeringElement();
    $action = $triggering_element ? end($form_state->getTriggeringElement()['#parents']) : NULL;
    $actions_map = [
      'select_entities' => 'select_attachments',
      'change_entities' => 'select_entities',
      'select_attachments' => 'select_attachments',
    ];

    $current_action = $values['current_action'];
    if ($action && array_key_exists($action, $actions_map)) {
      $current_action = $actions_map[$action];
    }
    if (empty($current_action)) {
      $current_action = empty($defaults['entities']['entity_ids']) ? 'select_entities' : 'select_attachments';
    }

    $element['current_action'] = [
      '#type' => 'hidden',
      '#value' => $current_action,
    ];

    $element['entities'] = [
      '#type' => 'entity_select',
      '#title' => t('Entity selection'),
      '#title_display' => 'invisible',
      '#default_value' => $defaults['entities'],
      '#multiple' => TRUE,
      '#element_context' => $element['#element_context'],
      '#entity_types' => $element['#entity_types'] ?? NULL,
    ];

    if ($current_action != 'select_entities') {
      $element['entities']['#hidden'] = TRUE;
    }
    else {
      $form_state->set('modal_title', $element['entities']['#title']);
    }

    $element['attachments'] = [
      '#type' => 'attachment_select',
      '#title' => t('Attachment selection'),
      '#title_display' => 'invisible',
      '#default_value' => $defaults['attachments'],
      '#multiple' => TRUE,
      '#element_context' => $element['#element_context'],
      '#entity_ids' => $defaults['entities']['entity_ids'],
      '#available_options' => $element['#attachment_options'] ?? NULL,
      '#attachment_type' => $element['#attachment_type'] ?? NULL,
    ];
    if ($current_action != 'select_attachments') {
      $element['attachments']['#hidden'] = TRUE;
    }
    else {
      $form_state->set('modal_title', $element['attachments']['#title']);
    }

    $element['actions'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'second-level-actions-wrapper',
          'entity-attachment-select-actions-wrapper',
        ],
      ],
    ];

    $element['actions']['select_entities'] = [
      '#type' => 'submit',
      '#value' => t('Use selected entities'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => [$current_action != 'select_entities' ? 'visually-hidden' : NULL],
      ],
    ];
    $element['actions']['change_entities'] = [
      '#type' => 'submit',
      '#value' => t('Change entities'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => [$current_action != 'select_attachments' ? 'visually-hidden' : NULL],
      ],
    ];
    $element['actions']['select_attachments'] = [
      '#type' => 'submit',
      '#value' => t('Use selected attachments'),
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#attributes' => [
        'class' => [$current_action != 'select_attachments' ? 'visually-hidden' : NULL],
      ],
    ];
    if (!empty($element['#next_step']) && !empty($element['#container_wrapper'])) {
      // If this element is part of a multistep form, we support that this
      // button might lead to a different subform.
      $element['actions']['select_attachments']['#next_step'] = $element['#next_step'];
      $element['actions']['select_attachments']['#ajax']['wrapper'] = $element['#container_wrapper'];
    }

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderEntityAttachmentSelect(array $element) {
    $element['#attributes']['type'] = 'entity_attachment_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-entity-attachment-select']);
    return $element;
  }

}
