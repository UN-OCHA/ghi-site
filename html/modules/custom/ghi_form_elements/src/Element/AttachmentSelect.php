<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides an attachment select element.
 *
 * @FormElement("attachment_select")
 */
class AttachmentSelect extends FormElement {

  use AjaxElementTrait;
  use SimpleCacheTrait;
  use AttachmentFilterTrait;

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
      '#available_options' => [],
      '#entity_ids' => [],
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
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processAttachmentSelect(array &$element, FormStateInterface $form_state) {
    $context = $element['#element_context'];
    $plan_id = $context['plan_object']->get('field_original_id')->value;

    $trigger = $form_state->getTriggeringElement() ? end($form_state->getTriggeringElement()['#parents']) : NULL;

    $triggered_by_select = $trigger ? in_array($trigger, [
      'entity_type',
      'attachment_type',
      'attachment_prototype',
    ]) : FALSE;
    $triggered_by_change_request = $trigger ? $trigger == 'change_attachment' : FALSE;
    $is_hidden = array_key_exists('#hidden', $element) && $element['#hidden'] && !$triggered_by_select && !$triggered_by_change_request;

    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="' . ($is_hidden ? 'visually-hidden' : NULL) . '">';
    $element['#suffix'] = '</div>';

    // Set the defaults.
    $submitted_values = array_filter((array) $form_state->getValue($element['#parents']));
    $values = $submitted_values + (array) $element['#default_value'];

    $defaults = [
      'entity_type' => !empty($values['entity_type']) ? $values['entity_type'] : ($element['#entity_type'] ?? NULL),
      'attachment_type' => !empty($values['attachment_type']) ? $values['attachment_type'] : ($element['#attachment_type'] ?? NULL),
      'attachment_prototype' => !empty($values['attachment_prototype']) ? $values['attachment_prototype'] : ($element['#attachment_prototype'] ?? NULL),
      'attachment_id' => !empty($values['attachment_id']) ? $values['attachment_id'] : NULL,
    ];

    $element['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $defaults['entity_type'],
    ];
    $element['attachment_type'] = [
      '#type' => 'hidden',
      '#value' => $defaults['attachment_type'],
    ];
    $element['attachment_prototype'] = [
      '#type' => 'hidden',
      '#value' => $defaults['attachment_prototype'],
    ];
    $element['attachment_id'] = [
      '#type' => 'hidden',
      '#value' => $defaults['attachment_id'],
    ];

    if ($element['#summary_only'] && !$triggered_by_select && !$triggered_by_change_request) {
      $attachment = self::getAttachmentQuery()->getAttachment($defaults['attachment_id']);
      $element['summary'] = [
        '#markup' => $attachment ? Markup::create($attachment->composed_reference) : t('No attachment selected.'),
      ];
      return $element;
    }

    if ($is_hidden) {
      return $element;
    }

    // Get the list of attachments that this element can access.
    $element_context_filter = array_filter([
      'entity_id' => $element['#entity_ids'] ?? NULL,
      'entity_type' => $defaults['entity_type'] ? ($defaults['entity_type'] !== 'overview' ? $defaults['entity_type'] . 'Entity' : 'plan') : NULL,
      'type' => $defaults['attachment_type'] ?? NULL,
      'prototype_id' => $defaults['attachment_prototype'] ?? NULL,
    ]);
    $attachment_cache_key = self::getCacheKey($element_context_filter);
    $attachments = $form_state->get($attachment_cache_key);
    if (!$attachments) {
      $attachments = self::getPlanEntitiesQuery($plan_id)->getDataAttachments($context['base_object'] ?? NULL, $element_context_filter);
      $form_state->set($attachment_cache_key, $attachments);
    }

    // Get the different options from the available set of all attachments in
    // the current base context.
    $entity_type_options = [];
    $attachment_type_options = [];
    $attachment_prototype_options = [];
    foreach ($attachments as $attachment) {
      $attachment_type_options[$attachment->type] = ucfirst($attachment->type);
      $attachment_prototype_options[$attachment->prototype->id] = $attachment->prototype->name . ' (' . $attachment->prototype->ref_code . ')';
    }

    // Build the filter to limit attachments to the once available using the
    // current filter values.
    $attachment_filter = array_filter([
      'source.entity_type' => $defaults['entity_type'] ? ($defaults['entity_type'] !== 'overview' ? $defaults['entity_type'] . 'Entity' : 'plan') : NULL,
      'type' => $defaults['attachment_type'] ?? NULL,
      'prototype.id' => $defaults['attachment_prototype'] ? (int) $defaults['attachment_prototype'] : NULL,
    ]);
    if (!empty($element['#entity_ids'])) {
      $attachment_filter['source.entity_id'] = $element['#entity_ids'];
    }

    // Apply the attachment filters and build the options array.
    $attachment_options = [];
    foreach (ArrayHelper::filterArray($attachments, $attachment_filter) as $attachment) {
      $attachment_options[$attachment->id] = [
        'id' => $attachment->id,
        'composed_reference' => $attachment->composed_reference,
        'type' => $attachment->type,
        'prototype' => $attachment->prototype->name,
        'description' => $attachment->description,
      ];
    }

    // Either show a select with the available options for the entity type, or
    // set a preset value that should come from $element['#entity_type'].
    if (!empty($element['#available_options']['entity_types'])) {
      $entity_type_options = $element['#available_options']['entities'];
      if (!empty($context['plan_object'])) {
        $entity_type_options = array_merge(['overview' => t('Plan')], $entity_type_options);
      }
      $element['entity_type'] = [
        '#type' => 'select',
        '#title' => t('Entity type'),
        '#options' => $entity_type_options,
        '#default_value' => $defaults['entity_type'],
        '#required' => TRUE,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $element['#disabled'],
      ];
      if (!empty($element['#entity_type'])) {
        $element['entity_type']['#type'] = 'hidden';
        $element['entity_type']['#value'] = $defaults['entity_type'];
      }
    }

    // Either show a select with the available options for the attachment type,
    // or set a preset value that should come from $element['#attachment_type'].
    if (!empty($element['#available_options']['attachment_types'])) {
      if (empty($defaults['attachment_type'])) {
        $defaults['attachment_type'] = reset($attachment_type_options);
      }
      $element['attachment_type'] = [
        '#type' => 'select',
        '#title' => t('Attachment type'),
        '#options' => $attachment_type_options,
        '#default_value' => $defaults['attachment_type'],
        '#required' => TRUE,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $element['#disabled'],
      ];
      if (!empty($element['#attachment_type'])) {
        $element['attachment_type']['#type'] = 'hidden';
        $element['attachment_type']['#value'] = $defaults['attachment_type'];
        unset($element['attachment_type']['#options']);
      }
    }

    // Either show a select with the available options for the attachment
    // prototype, or set a preset value that should come from
    // $element['#attachment_prototype'].
    if (in_array('attachment_prototypes', $element['#available_options']) && !empty($attachment_prototype_options)) {
      if (empty($defaults['attachment_prototype'])) {
        $defaults['attachment_prototype'] = reset($attachment_prototype_options);
      }
      $element['attachment_prototype'] = [
        '#type' => 'select',
        '#title' => t('Attachment prototype'),
        '#options' => $attachment_prototype_options,
        '#default_value' => $defaults['attachment_prototype'],
        '#required' => TRUE,
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $element['#disabled'],
      ];
      if (!empty($element['#attachment_prototype'])) {
        $element['attachment_prototype']['#type'] = 'hidden';
        $element['attachment_prototype']['#value'] = $defaults['attachment_prototype'];
        unset($element['attachment_prototype']['#options']);
      }
      elseif (count($attachment_prototype_options) == 1) {
        // Hide the selector if only a single prototype is found.
        $attachment_prototype_id = array_key_first($attachment_prototype_options);
        $defaults['attachment_prototype'] = $attachment_prototype_id;
        $element['attachment_prototype']['#type'] = 'hidden';
        $element['attachment_prototype']['#value'] = $attachment_prototype_id;
        unset($element['attachment_prototype']['#options']);
      }
    }

    $columns = [
      'id' => t('ID'),
      'composed_reference' => t('Reference'),
      'type' => t('Type'),
      'prototype' => t('Type'),
      'description' => t('Description'),
    ];

    $atachments_selected = (array) ($defaults['attachment_id'] ?? []);
    $element['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#required' => TRUE,
      '#header' => $columns,
      '#validated' => TRUE,
      '#options' => $attachment_options,
      '#default_value' => array_intersect($atachments_selected, array_keys($attachment_options)),
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
   * Get the endpoint query manager service.
   *
   * @return \Drupal\hpc_api\Query\EndpointQueryManager
   *   The endpoint query manager service.
   */
  private static function getEndpointQueryManager() {
    return \Drupal::service('plugin.manager.endpoint_query_manager');
  }

  /**
   * Get the attachment query service.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentQuery
   *   The attachment query plugin.
   */
  public static function getAttachmentQuery() {
    return self::getEndpointQueryManager()->createInstance('attachment_query');
  }

  /**
   * Get the attachment query service.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\AttachmentSearchQuery
   *   The attachment search query plugin.
   */
  public static function getAttachmentSearchQuery() {
    return self::getEndpointQueryManager()->createInstance('attachment_search_query');
  }

  /**
   * Get the plan entities query service.
   *
   * @param int $plan_id
   *   The plan id for which a query should be build.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanEntitiesQuery
   *   The plan entities query plugin.
   */
  public static function getPlanEntitiesQuery($plan_id) {
    $query_handler = self::getEndpointQueryManager()->createInstance('plan_entities_query');
    $query_handler->setPlaceholder('plan_id', $plan_id);
    return $query_handler;
  }

}
