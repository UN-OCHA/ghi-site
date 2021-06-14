<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

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

      '#multiple' => FALSE,
      '#disabled' => FALSE,
      '#summary_only' => FALSE,
      // '#show_filter' => FALSE,
      // '#group_by_entity' => FALSE,
      // '#include_available_map_metrics' => FALSE,
      // '#disable_empty_locations' => FALSE,
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
   * Process the usage year form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processAttachmentSelect(array &$element, FormStateInterface $form_state) {
    $context = $element['#element_context'];

    $trigger = $form_state->getTriggeringElement() ? end($form_state->getTriggeringElement()['#parents']) : NULL;
    $triggered_by_select = $trigger ? in_array($trigger, [
      'entity_type',
      'attachment_type',
    ]) : FALSE;
    $triggered_by_change_request = $trigger ? $trigger == 'change_attachment' : FALSE;
    $is_hidden = $element['#hidden'] && !$triggered_by_select && !$triggered_by_change_request;
    $class = NULL;

    if ($is_hidden) {
      $class = 'visually-hidden';
    }

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="' . $class . '">';
    $element['#suffix'] = '</div>';

    $entity_type_options = !empty($element['#entity_types']) ? $element['#entity_types'] : [
      'plan' => t('Plan entities'),
      'governing' => t('Governing entities'),
    ];

    $attachment_type_options = !empty($element['#attachment_types']) ? $element['#attachment_types'] : [
      'caseload' => t('Caseload'),
      'indicator' => t('Indicator'),
    ];

    // Set the defaults.
    $values = (array) $form_state->getValue($element['#parents']) + (array) $element['#default_value'];
    $defaults = [
      'entity_type' => !empty($values['entity_type']) ? $values['entity_type'] : NULL,
      'attachment_type' => !empty($values['attachment_type']) ? $values['attachment_type'] : NULL,
      'attachment_id' => !empty($values['attachment_id']) ? $values['attachment_id'] : NULL,
    ];

    if ($element['#summary_only'] && !$triggered_by_select && !$triggered_by_change_request) {
      $attachment = self::getAttachmentQuery()->getAttachment($defaults['attachment_id']);
      $element['entity_type'] = [
        '#type' => 'value',
        '#value' => $defaults['entity_type'],
      ];
      $element['attachment_type'] = [
        '#type' => 'value',
        '#value' => $defaults['attachment_type'],
      ];
      $element['attachment_id'] = [
        '#type' => 'value',
        '#value' => $defaults['attachment_id'],
      ];
      $element['summary'] = [
        '#markup' => $attachment ? Markup::create($attachment->composed_reference) : t('No attachment selected.'),
      ];
      return $element;
    }
    if ($is_hidden) {
      $element['entity_type'] = [
        '#type' => 'value',
        '#value' => $defaults['entity_type'],
      ];
      $element['attachment_type'] = [
        '#type' => 'value',
        '#value' => $defaults['attachment_type'],
      ];
      $element['attachment_id'] = [
        '#type' => 'value',
        '#value' => $defaults['attachment_id'],
      ];
      return $element;
    }

    if (!empty($context['page_node']) && $context['page_node']->bundle() == 'plan') {
      $entity_type_options = array_merge(['overview' => t('Plan')], $entity_type_options);
    }
    // Either show a select with the available options for the entity type, or
    // set a preset value that should come from $element['#entity_type'].
    $element['entity_type'] = [
      '#type' => 'select',
      '#title' => t('Entity type'),
      '#options' => $entity_type_options,
      '#default_value' => $defaults['entity_type'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#disabled' => $element['#disabled'],
    ];

    if (!empty($element['#entity_type'])) {
      $defaults['entity_type'] = $element['#entity_type'];
      $element['entity_type']['#type'] = 'hidden';
      $element['entity_type']['#value'] = $defaults['entity_type'];
    }

    // Either show a select with the available options for the attachment type,
    // or set a preset value that should come from $element['#attachment_type'].
    $element['attachment_type'] = [
      '#type' => 'select',
      '#title' => t('Attachment type'),
      '#options' => $attachment_type_options,
      '#default_value' => $defaults['attachment_type'],
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#disabled' => $element['#disabled'],
    ];
    if (!empty($element['#attachment_type'])) {
      $defaults['attachment_type'] = $element['#attachment_type'];
      $element['attachment_type']['#type'] = 'hidden';
      $element['attachment_type']['#value'] = $defaults['attachment_type'];
      unset($element['attachment_type']['#options']);
    }

    $attachments = self::getPlanEntitiesQuery()->getDataAttachments($context['page_node'], [
      'type' => $defaults['attachment_type'],
    ]);
    $attachment_options = [];
    foreach ($attachments as $attachment) {
      $attachment_options[$attachment->id] = [
        'id' => $attachment->id,
        'composed_reference' => $attachment->composed_reference,
        'type' => $attachment->type,
        'prototype' => $attachment->prototype->name,
        'description' => $attachment->description,
      ];
    }

    $columns = [
      'id' => t('ID'),
      'composed_reference' => t('Reference'),
      'type' => t('Type'),
      'prototype' => t('Prototype'),
      'description' => t('Description'),
    ];

    $element['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => TRUE,
      '#header' => $columns,
      '#validated' => TRUE,
      '#options' => $attachment_options,
      '#default_value' => array_key_exists($defaults['attachment_id'], $attachment_options) ? $defaults['attachment_id'] : NULL,
      '#multiple' => $element['#multiple'],
      '#disabled' => $element['#disabled'],
      '#empty' => t('No suitable attachments found. Please review your selection criteria above.'),
    ];
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderAttachmentSelect(array $element) {
    $element['#attributes']['type'] = 'attachment_select';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-attachment-select']);
    return $element;
  }

  /**
   * Get the attachment query service.
   *
   * @return \Drupal\ghi_plans\Query\AttachmentQuery
   *   The attachment query service.
   */
  public static function getAttachmentQuery() {
    return \Drupal::service('ghi_plans.attachment_query');
  }

  /**
   * Get the plan entities query service.
   *
   * @return \Drupal\ghi_plans\Query\PlanEntitiesQuery
   *   The plan entities query service.
   */
  public static function getPlanEntitiesQuery() {
    return \Drupal::service('ghi_plans.plan_entities_query');
  }

}
