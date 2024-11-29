<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;
use Drupal\node\NodeInterface;

/**
 * Provides an attachment select element.
 *
 * @FormElement("custom_link")
 */
class CustomLink extends FormElementBase {

  use CustomLinkTrait;

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
        [$class, 'processCustomLink'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCustomLink'],
        [$class, 'preRenderGroup'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#element_context' => [],
      '#required' => FALSE,
      '#no_label' => FALSE,
    ];
  }

  /**
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processCustomLink(array &$element, FormStateInterface $form_state) {
    $default_values = (array) $element['#default_value'];
    $uri = $default_values['link_custom']['url'] ?? NULL;
    $element['#attached']['library'][] = 'ghi_form_elements/custom_link';

    $display_url = NULL;
    if ($uri) {
      try {
        // The current field value could have been entered by a different user.
        // However, if it is inaccessible to the current user, do not display it
        // to them.
        $url = Url::fromUri($uri);
        if (\Drupal::currentUser()->hasPermission('link to any page') || $url?->access()) {
          $display_url = static::getUriAsDisplayableString($uri);
        }
      }
      catch (\InvalidArgumentException $e) {
        // If $uri is invalid, show value as is, so the user can see what
        // to edit.
        // @todo Add logging here in https://www.drupal.org/project/drupal/issues/3348020
        $display_url = $uri;
      }
    }

    $element_context = $element['#element_context'];
    $section_node = $element_context['section_node'] instanceof SectionNodeInterface ? $element_context['section_node'] : NULL;
    $page_node = $element_context['page_node'] instanceof NodeInterface ? $element_context['page_node'] : NULL;
    $targets = $section_node && $page_node ? self::getLinkTargetOptions($section_node, $page_node) : [];
    $required = $element['#required'];
    $state_selector_add_link = NULL;

    if (!$required) {
      $element['add_link'] = [
        '#type' => 'checkbox',
        '#title' => t('Add a link to this element'),
        '#default_value' => !empty($default_values['add_link']) ? $default_values['add_link'] : FALSE,
        '#weight' => -1,
      ];
      $state_selector_add_link = FormElementHelper::getStateSelector($element, ['add_link']);
    }

    $element['link'] = [
      '#type' => 'fieldset',
      '#collapsible' => FALSE,
      '#attributes' => [
        'class' => ['link-wrapper'],
      ],
      // Set the parents to be the same as the original element. It would make
      // more sense to set #tree to false, but that somehow messes with the
      // submission of the form values.
      '#parents' => $element['#parents'],
      '#states' => [
        'visible' => array_filter([
          'input[name="' . $state_selector_add_link . '"]' => !$required ? ['checked' => TRUE] : NULL,
        ]),
      ],
    ];

    $element['link']['label'] = [
      '#type' => 'textfield',
      '#title' => t('Link label'),
      '#description' => t('Enter an optional label for the link. If left empty, "Go to page" will be used for internal urls and "Open" for external urls.'),
      '#default_value' => !empty($default_values['label']) ? $default_values['label'] : NULL,
      '#access' => empty($element['link']['#no_label']),
    ];
    $element['link']['link_type'] = [
      '#type' => 'radios',
      '#title' => t('Link type'),
      '#options' => [
        'related' => t('Associated pages'),
        'custom' => t('Custom link'),
      ],
      '#default_value' => !empty($default_values['link_type']) ? $default_values['link_type'] : 'related',
      '#attributes' => [
        'class' => [
          'form-type--link-type',
        ],
      ],
    ];
    if (empty($targets)) {
      $element['link']['link_type']['#default_value'] = 'custom';
      $element['link']['link_type']['#disabled'] = TRUE;
      $element['link']['link_type']['#description'] = t('<strong>Note:</strong> The link type is set to <em>custom</em> because there are no target links available in the current page context.');
    }

    $state_selector = FormElementHelper::getStateSelector($element, ['link_type']);
    $element['link']['link_custom'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => array_filter([
          'input[name="' . $state_selector . '"]' => !empty($targets) ? ['value' => 'custom'] : NULL,
        ]),
      ],
    ];
    $element['link']['link_custom']['url'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('Url'),
      '#default_value' => $display_url,
      '#description' => t('Start typing the title of a piece of content to select it. You can also enter an external URL such as %url.', [
        '%url' => 'http://example.com',
      ]),
      '#link_type' => LinkItemInterface::LINK_GENERIC,
      '#target_type' => 'node',
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
      '#element_validate' => [
        [LinkWidget::class, 'validateUriElement'],
      ],
      '#required' => FALSE,
      '#maxlength' => 255,
    ];

    $element['link']['link_related'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#states' => [
        'visible' => array_filter([
          'input[name="' . $state_selector . '"]' => ['value' => 'related'],
        ]),
      ],
      '#access' => !empty($targets),
    ];
    $element['link']['link_related']['target'] = [
      '#type' => 'select',
      '#title' => t('Link target'),
      '#options' => $targets,
      '#default_value' => !empty($default_values['link_related']['target']) ? $default_values['link_related']['target'] : array_key_first($targets),
    ];

    unset($element['#title']);
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderCustomLink(array $element) {
    $element['#attributes']['type'] = 'custom_link';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-custom-link']);
    return $element;
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
  public static function elementValidate(array &$element, FormStateInterface $form_state, array $form) {
    $value = $element['#value'];
    if (empty($value['add_link']) || empty($value['link_type'])) {
      return;
    }
    if ($value['link_type'] == 'custom') {
      $url = $value['link_custom']['url'];
      $transformed_url = self::transformUrl($url);
      if (!$url || !$transformed_url) {
        $form_state->setError($element['link']['link_custom']['url'], t('The link URL must be valid and accessible.'));
      }
      if (!$form_state->hasAnyErrors() && $transformed_url !== $url) {
        $element['#value']['link_custom']['url'] = $transformed_url;
        $form_state->setValueForElement($element, $element['#value']);
      }
    }
  }

}
