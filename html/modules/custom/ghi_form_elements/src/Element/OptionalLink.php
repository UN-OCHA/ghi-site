<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Url;
use Drupal\ghi_form_elements\Helpers\FormElementHelper;
use Drupal\ghi_form_elements\Traits\OptionalLinkTrait;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides an attachment select element.
 *
 * @FormElement("optional_link")
 */
class OptionalLink extends FormElement {

  use OptionalLinkTrait;

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
        [$class, 'processOptionalLink'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderOptionalLink'],
        [$class, 'preRenderGroup'],
      ],
      '#element_validate' => [
        [$class, 'elementValidate'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processOptionalLink(array &$element, FormStateInterface $form_state) {
    $default_values = (array) $element['#default_value'];
    $uri = $default_values['link']['url'] ?? NULL;

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

    $element['add_link'] = [
      '#type' => 'checkbox',
      '#title' => $element['#title'],
      '#default_value' => !empty($default_values['add_link']),
    ];
    $state_selector = FormElementHelper::getStateSelector($element, ['add_link']);
    $element['link'] = [
      '#type' => 'container',
      '#states' => [
        'visible' => [
          'input[name="' . $state_selector . '"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['link']['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#description' => t('Enter a label for the link.'),
      '#default_value' => $default_values['link']['label'] ?? NULL,
    ];
    $element['link']['url'] = [
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
    ];
    unset($element['#title']);
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderOptionalLink(array $element) {
    $element['#attributes']['type'] = 'optional_link';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-optional-link']);
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
    if (empty($value['add_link']) || empty($value['link']['url'])) {
      return;
    }
    $url = $value['link']['url'];
    $transformed_url = self::transformUrl($url);
    if ($transformed_url !== $url) {
      $element['#value']['link']['url'] = $transformed_url;
      $form_state->setValueForElement($element, $element['#value']);
    }
  }

}
