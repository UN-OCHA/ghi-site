<?php

namespace Drupal\ghi_blocks\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_form_elements\Traits\OptionalLinkTrait;
use Drupal\link\LinkItemInterface;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a carousel item element.
 *
 * @FormElement("carousel_item")
 */
class CarouselItem extends FormElement {

  use OptionalLinkTrait;

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
   * Process the carousel item form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processCarouselItem(array &$element, FormStateInterface $form_state) {
    $element['tag_line'] = [
      '#type' => 'textfield',
      '#title' => t('Tag line'),
      '#default_value' => $element['#default_value']['tag_line'] ?? NULL,
      '#description' => t('Enter a tag line that will appear above the title.'),
    ];
    $element['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $element['#default_value']['description'] ?? NULL,
      '#description' => t('Enter a short description for this link.'),
    ];
    $element['image'] = [
      '#type' => 'managed_file',
      '#title' => t('Image'),
      '#upload_location' => self::THUMBNAIL_DIRECTORY,
      '#upload_validators' => [
        'file_validate_extensions' => ['jpg jpeg png gif'],
      ],
      '#default_value' => $element['#default_value']['image'] ?? NULL,
    ];
    $element['image_credit'] = [
      '#type' => 'textfield',
      '#title' => t('Image credit'),
      '#default_value' => $element['#default_value']['image_credit'] ?? NULL,
      '#description' => t('Enter a credit for the image. This will appear on top of the image.'),
    ];
    $element['image_caption'] = [
      '#type' => 'textfield',
      '#title' => t('Image caption'),
      '#default_value' => $element['#default_value']['image_caption'] ?? NULL,
      '#description' => t('Enter a caption for the image. This will appear together with the image credit on top of the image.'),
    ];
    $element['url'] = [
      '#type' => 'entity_autocomplete',
      '#title' => t('Url'),
      '#default_value' => self::getUriAsDisplayableString($element['#default_value']['url']) ?? NULL,
      '#description' => t('Start typing the title of a piece of content to select it. You can also enter an external URL such as %url.', [
        '%url' => 'http://example.com',
      ]),
      '#link_type' => LinkItemInterface::LINK_GENERIC,
      '#target_type' => 'node',
      '#attributes' => [
        'data-autocomplete-first-character-blacklist' => '/#?',
      ],
      '#process_default_value' => FALSE,
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
      '#maxlength' => 256,
    ];
    $element['button_label'] = [
      '#type' => 'textfield',
      '#title' => t('Button label'),
      '#default_value' => $element['#default_value']['button_label'] ?? NULL,
      '#description' => t('Optional button label. Leave empty to use a default label.'),
    ];
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderCarouselItem(array $element) {
    $element['#attributes']['type'] = 'carousel_item';
    Element::setAttributes($element, ['id', 'name']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-carousel-item']);
    return $element;
  }

}
