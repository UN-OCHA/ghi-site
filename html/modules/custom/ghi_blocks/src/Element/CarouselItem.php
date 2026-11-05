<?php

namespace Drupal\ghi_blocks\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\ghi_blocks\Helpers\LinkCarouselLink;

/**
 * Provides a carousel item element.
 */
#[FormElement('carousel_item')]
class CarouselItem extends FormElementBase {

  use CustomLinkTrait;

  const THUMBNAIL_DIRECTORY = 'public://content-panes/carousel-items/';

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
        [$class, 'processCarouselItem'],
      ],
      '#pre_render' => [
        [$class, 'preRenderCarouselItem'],
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
    if ($input !== NULL) {
      // Make sure input is returned as normal during item configuration.
      return $input;
    }
    return NULL;
  }

  /**
   * Builds the fields for editing a carousel item.
   */
  public static function processCarouselItem(array &$element, FormStateInterface $form_state) {
    $element['publication_date'] = [
      '#type' => 'date',
      '#title' => t('Publication date'),
      '#default_value' => $element['#default_value']['publication_date'] ?? '',
      '#description' => t('Optional date shown with a featured item. Leave empty when the publication date is unknown.'),
    ];
    $element['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $element['#default_value']['description'] ?? NULL,
      '#description' => t('Enter a short description. It is displayed only when this item is Main news.'),
    ];
    $element['image'] = [
      '#type' => 'managed_file',
      '#title' => t('Image'),
      '#upload_location' => self::THUMBNAIL_DIRECTORY,
      '#upload_validators' => [
        'FileExtension' => ['extensions' => 'jpg jpeg png gif'],
      ],
      '#default_value' => $element['#default_value']['image'] ?? NULL,
    ];
    $element['image_alt'] = [
      '#type' => 'textfield',
      '#title' => t('Alternative text'),
      '#default_value' => $element['#default_value']['image_alt'] ?? '',
      '#description' => t('Describe the image for people who cannot see it. Leave empty only when the image is decorative.'),
    ];
    $element['image_credit'] = [
      '#type' => 'textfield',
      '#title' => t('Image credit'),
      '#default_value' => $element['#default_value']['image_credit'] ?? NULL,
      '#description' => t('Enter a credit for the image. It is displayed below the image only when this item is Main news.'),
    ];
    $element['image_caption'] = [
      '#type' => 'textfield',
      '#title' => t('Image caption'),
      '#default_value' => $element['#default_value']['image_caption'] ?? NULL,
      '#description' => t('Enter a caption for the image. It is displayed below the image, together with the credit, only when this item is Main news.'),
    ];
    $stored_url = (string) ($element['#default_value']['url'] ?? '');
    $uri = LinkCarouselLink::normalize($stored_url);
    $element['url'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('Url'),
      // The display converter assumes a valid URI; drafts may contain anything.
      '#default_value' => $uri !== NULL ? self::getUriAsDisplayableString($uri) : $stored_url,
      '#description' => t('Start typing the title of a piece of content to select it. You can also enter an external URL such as %url.', [
        '%url' => 'http://example.com',
      ]),
      '#target_type' => 'node',
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
      '#element_validate' => [[static::class, 'validateUrlElement']],
      '#maxlength' => 256,
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => t('Button label'),
      '#default_value' => $element['#default_value']['button_label'] ?? NULL,
      '#description' => t('Optional button label for Main news. Leave empty to use "Read more". Featured items link from their titles.'),
    ];
    return $element;
  }

  /**
   * Normalizes supported links while retaining invalid input for later repair.
   *
   * @param array $element
   *   The URL form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   */
  public static function validateUrlElement(array $element, FormStateInterface $form_state, array $complete_form): void {
    $value = trim($element['#value'] ?? '');
    $entity_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($value);
    if ($entity_id !== NULL) {
      $value = 'entity:node/' . $entity_id;
    }
    elseif (str_starts_with($value, '<front>')) {
      $value = '/' . substr($value, strlen('<front>'));
    }
    $uri = LinkCarouselLink::normalize($value);
    if ($uri !== NULL) {
      $form_state->setValueForElement($element, $uri);
    }
  }

  /**
   * Prerender callback.
   */
  public static function preRenderCarouselItem(array $element) {
    $element['#attributes']['type'] = 'carousel_item';
    Element::setAttributes($element, ['id', 'name']);
    // Add the standard form classes, including error highlights when needed.
    static::setAttributes($element, ['form-carousel-item']);
    return $element;
  }

}
