<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;
use Drupal\ghi_plans\Traits\AttachmentFilterTrait;
use Drupal\hpc_api\Helpers\ArrayHelper;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides an attachment select element.
 */
#[FormElement('attachment_select')]
class AttachmentSelect extends FormElementBase {

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
      '#available_options' => [
        // The keys can be either 'entity_types', 'attachment_type' or
        // 'attachment_prototypes'.
      ],
      '#entity_ids' => [],
      '#attachment_type' => NULL,
      '#disagg_warning' => FALSE,
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
      if (is_array($input) && array_key_exists('attachment_id', $input)) {
        $input['attachment_id'] = array_filter((array) $input['attachment_id']);
      }
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
    $element['#attached']['library'][] = 'ghi_form_elements/attachment_select';

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
    $classes = ['ghi-element-wrapper'];
    $element['#prefix'] = '<div id="' . $wrapper_id . '" class="' . implode(' ', $classes) . ($is_hidden ? ' visually-hidden' : NULL) . '">';
    $element['#suffix'] = '</div>';

    // Set the defaults from the submitted values and filters.
    $submitted_values = array_filter((array) $form_state->getValue($element['#parents']));
    $values = $submitted_values + (array) $element['#default_value'];
    $filters = $values['filter'] ?? [];

    // These are really the filters.
    $defaults = [
      'entity_type' => $filters['entity_type'] ?? NULL,
      'attachment_type' => !empty($filters['attachment_type']) ? $filters['attachment_type'] : NULL,
      'attachment_prototype' => !empty($filters['attachment_prototype']) ? $filters['attachment_prototype'] : NULL,
      'attachment_id' => !empty($values['attachment_id']) ? array_filter((array) $values['attachment_id']) : [],
    ];

    // Get the list of attachments that this element can access.
    $element_context_filter = array_filter([
      'entity_id' => $element['#entity_ids'] ?? NULL,
      'entity_type' => $element['#entity_type'] ?? NULL,
      'type' => $element['#attachment_type'] ?? NULL,
    ]);

    // Get the attachments.
    $attachments = self::getPlanEntitiesQuery($plan_id)->getDataAttachments($context['base_object'] ?? NULL, $element_context_filter);

    // Get the different options from the available set of all attachments in
    // the current base context.
    $entity_type_options = [];
    $attachment_type_options = [];
    $attachment_prototype_options = [];
    foreach ($attachments as $attachment) {
      if ($source_entity = $attachment->getSourceEntity()) {
        $entity_type_options[$source_entity->getEntityType()] = $source_entity->getEntityTypeName();
      }
      $attachment_type_options[$attachment->type] = ucfirst($attachment->type);
      $attachment_prototype_options[$attachment->prototype->id] = $attachment->prototype->name . ' (' . $attachment->prototype->ref_code . ')';
    }
    krsort($attachment_prototype_options);

    // Sanity check to handle imported code from other plan pages.
    if (empty($attachment_prototype_options) || ($defaults['attachment_prototype'] && !array_key_exists($defaults['attachment_prototype'], $attachment_prototype_options))) {
      $defaults['attachment_prototype'] = NULL;
    }

    // Setup the filters base form structure.
    $element['filter'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#weight' => 0,
      '#attributes' => [
        'class' => 'filter-container',
      ],
    ];
    $element['filter']['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $defaults['entity_type'],
    ];
    $element['filter']['attachment_type'] = [
      '#type' => 'hidden',
      '#value' => $defaults['attachment_type'],
    ];
    $element['filter']['attachment_prototype'] = [
      '#type' => 'hidden',
      '#value' => $defaults['attachment_prototype'],
    ];
    // It is important to replicate the nested structure here, otherwise the
    // form builder will create errors when trying to set NULL values in the
    // form structure using NestedArray::setValue().
    if (!empty($defaults['attachment_id'])) {
      if ($element['#multiple']) {
        $element['attachment_id'] = [
          '#type' => 'container',
        ];
        foreach ($defaults['attachment_id'] as $attachment_id) {
          $element['attachment_id'][$attachment_id] = [
            '#type' => 'hidden',
            '#value' => $attachment_id,
          ];
        }
      }
      else {
        $attachment_ids = (array) $defaults['attachment_id'];
        $element['attachment_id'] = [
          '#type' => 'hidden',
          '#value' => reset($attachment_ids),
        ];
      }
    }

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

    if (!empty($context['plan_object'])) {
      $entity_type_options = array_merge(['plan' => (string) t('Plan')], $entity_type_options);
    }

    // Build the filter to limit attachments to the ones available using the
    // current filter values.
    $attachment_filter = array_filter([
      'source.entity_type' => $defaults['entity_type'] ?? NULL,
      'type' => $defaults['attachment_type'] ?? NULL,
      'prototype.id' => $defaults['attachment_prototype'] ? (int) $defaults['attachment_prototype'] : NULL,
    ]);
    if (!empty($element['#entity_ids'])) {
      $attachment_filter['source.entity_id'] = $element['#entity_ids'];
    }

    // Apply the attachment filters and build the options array.
    $attachment_options = [];
    $entities_in_selection = [];
    foreach (ArrayHelper::filterArray($attachments, $attachment_filter) as $attachment) {
      /** @var \Drupal\ghi_plans\ApiObjects\Attachments\DataAttachment $attachment */
      $entities_in_selection[$attachment->source->entity_id] = TRUE;
      $attachment_options[$attachment->id] = [
        'id' => $attachment->id(),
        'composed_reference' => $attachment->composed_reference,
        'type' => $attachment->type,
        'prototype' => $attachment->prototype->name,
        'description' => $attachment->description,
        'sort_key' => $attachment->getSourceEntity()?->sort_key,
      ];

      if (!empty($element['#disagg_warning'])) {
        $attachment_options[$attachment->id]['disagg_data'] = $attachment->hasDisaggregatedData() ? '✓' : '✗';
      }
    }
    ArrayHelper::sortArrayByStringKey($attachment_options, 'composed_reference');
    ArrayHelper::sortArrayByStringKey($attachment_options, 'sort_key');

    // Either show a select with the available options for the entity type, or
    // set a preset value that should come from $element['#entity_type'].
    if (!empty($element['#available_options']['entity_types'])) {
      $element['filter']['entity_type'] = [
        '#type' => 'select',
        '#title' => t('Entity type'),
        '#options' => $entity_type_options,
        '#default_value' => $defaults['entity_type'],
        // This should be actually wrong, but it works. And it's needed to
        // prevent "Invalid choice" errors when first showing the filters.
        '#value' => $defaults['entity_type'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $element['#disabled'],
      ];
      if (!empty($element['#entity_type'])) {
        $element['filter']['entity_type']['#type'] = 'hidden';
        $element['filter']['entity_type']['#value'] = NULL;
      }
      elseif (count($entity_type_options) == 1) {
        // Hide the selector if only a single prototype is found.
        $element['filter']['entity_type']['#prefix'] = '<div class="visually-hidden">';
        $element['filter']['entity_type']['#suffix'] = '</div>';
      }
    }

    // Either show a select with the available options for the attachment type,
    // or set a preset value that should come from $element['#attachment_type'].
    if (!empty($element['#available_options']['attachment_types'])) {
      if (empty($defaults['attachment_type'])) {
        $defaults['attachment_type'] = reset($attachment_type_options);
      }
      $element['filter']['attachment_type'] = [
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
        $element['filter']['attachment_type']['#type'] = 'hidden';
        $element['filter']['attachment_type']['#value'] = NULL;
      }
    }

    // Either show a select with the available options for the attachment
    // prototype, or set a preset value that should come from
    // $element['#attachment_prototype'].
    if (!empty($element['#available_options']['attachment_prototypes']) && !empty($attachment_prototype_options)) {
      $element['filter']['attachment_prototype'] = [
        '#type' => 'select',
        '#title' => t('Attachment prototype'),
        '#options' => ['' => t('- All -')] + $attachment_prototype_options,
        '#default_value' => $defaults['attachment_prototype'],
        '#ajax' => [
          'event' => 'change',
          'callback' => [static::class, 'updateAjax'],
          'wrapper' => $wrapper_id,
        ],
        '#disabled' => $element['#disabled'],
      ];
      if (!empty($element['#attachment_prototype'])) {
        $element['filter']['attachment_prototype']['#type'] = 'hidden';
        $element['filter']['attachment_prototype']['#value'] = NULL;
      }
      elseif (count($attachment_prototype_options) == 1) {
        // Hide the selector if only a single prototype is found.
        $element['filter']['attachment_prototype']['#prefix'] = '<div class="visually-hidden">';
        $element['filter']['attachment_prototype']['#suffix'] = '</div>';
      }
    }

    // @codingStandardsIgnoreStart
    // $show_filter = TRUE;
    // if ($show_filter) {
    //   $element['filter']['text'] = [
    //     '#type' => 'textfield',
    //     '#title' => t('Search'),
    //     '#description' => t('Optionally add a search string to limit the available options.'),
    //     '#filter_key' => 'description',
    //   ];
    // }
    // @codingStandardsIgnoreEnd

    $columns = [
      'id' => t('ID'),
      'composed_reference' => t('Reference'),
      'prototype' => t('Type'),
      'description' => t('Description'),
    ];
    if (!empty($element['#disagg_warning'])) {
      $columns['disagg_data'] = t('Disaggregated data');
    }

    // Build the explanation that should show above the attachment select table.
    $header_parts[] = $element['#multiple'] ? t('Select the attachments that you want to use.') : t('Select the attachment that you want to use.');

    $element['header'] = [
      '#type' => 'markup',
      '#markup' => t('Found @count attachments in @count_entities entities matching your selection.', [
        '@count' => count($attachment_options),
        '@count_entities' => count($entities_in_selection),
      ]) . '<br />' . implode(' ', $header_parts),
      '#prefix' => '<div>',
      '#suffix' => '</div><br />',
      '#weight' => 9,
    ];

    // Filter the selected attachments to the ones actually available in the
    // current option set.
    $attachments_selected = (array) ($defaults['attachment_id'] ?? []);
    $default_attachments = array_intersect($attachments_selected, array_keys($attachment_options));
    if (!empty($default_attachments) && empty($element['#multiple'])) {
      $default_attachments = array_key_first($default_attachments);
    }
    if (empty($default_attachments) && empty($element['#multiple'])) {
      $default_attachments = array_key_first($attachment_options);
    }

    // Also update the user input to prevent validation errors when switching
    // any of the filters so that a currently selected option is not available
    // anymore.
    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, array_merge($element['#parents'], ['attachment_id']), $default_attachments);
    $form_state->setUserInput($user_input);

    $element['attachment_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $columns,
      '#options' => $attachment_options,
      '#default_value' => $default_attachments,
      '#multiple' => $element['#multiple'],
      '#disabled' => $element['#disabled'],
      '#empty' => t('No suitable attachments found.'),
      '#weight' => 10,
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
