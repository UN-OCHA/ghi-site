<?php

namespace Drupal\ghi_blocks\Element;

use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a carousel item element.
 *
 * @FormElement("carousel_item")
 */
class CarouselItem extends FormElement {

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
    ];
    $element['description'] = [
      '#type' => 'textarea',
      '#title' => t('Description'),
      '#default_value' => $element['#default_value']['description'] ?? NULL,
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
    ];
    $element['image_caption'] = [
      '#type' => 'textfield',
      '#title' => t('Image caption'),
      '#default_value' => $element['#default_value']['image_caption'] ?? NULL,
    ];
    $element['url'] = [
      '#type' => 'path',
      '#title' => t('Url'),
      '#default_value' => static::getUriAsDisplayableString($element['#default_value']['url']) ?? NULL,
      '#element_validate' => [[LinkWidget::class, 'validateUriElement']],
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

  /**
   * Gets the URI without the 'internal:' or 'entity:' scheme.
   *
   * The following two forms of URIs are transformed:
   * - 'entity:' URIs: to entity autocomplete ("label (entity id)") strings;
   * - 'internal:' URIs: the scheme is stripped.
   *
   * This method is the inverse of ::getUserEnteredStringAsUri().
   *
   * This method has been copied from LinkWidget::getUriAsDisplayableString().
   *
   * @param string $uri
   *   The URI to get the displayable string for.
   *
   * @return string
   *   The uri as a displayable (human-readably) string.
   *
   * @see LinkWidget::getUserEnteredStringAsUri()
   */
  protected static function getUriAsDisplayableString($uri) {
    $scheme = parse_url($uri, PHP_URL_SCHEME);

    // By default, the displayable string is the URI.
    $displayable_string = $uri;

    // A different displayable string may be chosen in case of the 'internal:'
    // or 'entity:' built-in schemes.
    if ($scheme === 'internal') {
      $uri_reference = explode(':', $uri, 2)[1];

      // @todo '<front>' is valid input for BC reasons, may be removed by
      //   https://www.drupal.org/node/2421941
      $path = parse_url($uri, PHP_URL_PATH);
      if ($path === '/') {
        $uri_reference = '<front>' . substr($uri_reference, 1);
      }

      $displayable_string = $uri_reference;
    }
    elseif ($scheme === 'entity') {
      [$entity_type, $entity_id] = explode('/', substr($uri, 7), 2);
      // Show the 'entity:' URI as the entity autocomplete would.
      // @todo Support entity types other than 'node'. Will be fixed in
      //   https://www.drupal.org/node/2423093.
      if ($entity_type == 'node' && $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id)) {
        $displayable_string = EntityAutocomplete::getEntityLabels([$entity]);
      }
    }
    elseif ($scheme === 'route') {
      $displayable_string = ltrim($displayable_string, 'route:');
    }

    return $displayable_string;
  }

}
