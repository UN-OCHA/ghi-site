<?php

namespace Drupal\ghi_form_elements\Element;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Markup;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Provides a SmugMug image element.
 *
 * @FormElement("smugmug_image")
 */
class SmugmugImage extends FormElement {

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
        [$class, 'processSmugmugImage'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderSmugmugImage'],
        [$class, 'preRenderGroup'],
      ],
      '#element_submit' => [
        [$class, 'elementSubmit'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#multiple' => FALSE,
      '#smugmug_user_scope' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== NULL) {
      return $input;
    }
    return NULL;
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
    $action = self::getActionFromFormState($form_state);
    if ($action != 'submit') {
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Process the attachment select form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processSmugmugImage(array &$element, FormStateInterface $form_state) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    // Get the set of current values for this element.
    $values = NestedArray::mergeDeepArray([
      (array) $element['#default_value'],
      (array) $form_state->getValue($element['#parents']),
    ], TRUE);

    // See what action has been taken by the user.
    $action = self::getActionFromFormState($form_state);
    if ($action) {
      $editing = $form_state->get('editing') ?? FALSE;
      if ($action == 'change_image' || $action == 'apply') {
        $editing = TRUE;
      }
      if ($action == 'select_cancel') {
        $editing = FALSE;
      }
      if ($action == 'select_image') {
        $editing = FALSE;
        $values['image_id'] = $values['search']['image_id'];
      }
      $form_state->set('editing', $editing);
    }
    $editing = $form_state->get('editing') ?? FALSE;

    // Setup the services we need.
    $date_formatter = self::getDateFormatter();
    $image_service = self::getSmugmugImageService();
    $image_search = self::getSmugmugImageSearchService();
    if (!$image_service || !$image_search) {
      return $element;
    }
    $smugmug_ocha = $element['#smugmug_user_scope'];
    if (!$smugmug_ocha) {
      return $element;
    }

    // Take over any states settings so that they can apply.
    $states = $element['#states'] ?? [];

    // Setup the general table layout, both for the preview summary and the
    // image selection.
    $table_header = [
      'preview' => t('Preview'),
      'caption' => t('Title / Caption'),
      'date' => t('Date'),
    ];

    // This stores the selected image.
    $element['image_id'] = [
      '#type' => 'hidden',
      '#value' => $values['image_id'] ?? NULL,
    ];

    // Setup the preview summary.
    $element['currently_selected'] = [
      '#type' => 'container',
      '#states' => $states,
      '#attributes' => [
        'class' => [$editing ? 'visually-hidden' : NULL],
      ],
    ];
    $element['currently_selected']['preview'] = [
      '#type' => 'table',
      '#caption' => Markup::create('<strong>' . t('Currently selected') . '</strong><p />'),
      '#header' => $table_header,
      '#rows' => [],
      '#empty' => t('No image selected yet'),
    ];
    if (!empty($values['image_id'])) {
      $image = $image_service->getImage($values['image_id']);

      $element['currently_selected']['preview']['#rows'][] = [
        'preview' => [
          'data' => [
            '#theme' => 'imagecache_external',
            '#style_name' => 'thumbnail',
            '#uri' => $image['ThumbnailUrl'],
          ],
        ],
        'caption' => [
          'data' => [
            '#markup' => (!empty($image['Title']) ? new FormattableMarkup('<strong>@title</strong><br />', ['@title' => $image['Title']]) : NULL) . $image['Caption'],
          ],
        ],
        'date' => $date_formatter->format(strtotime($image['Date']), 'custom', 'd M Y'),
      ];
    }
    $element['currently_selected']['change_image'] = [
      '#type' => 'button',
      '#value' => t('Change image'),
      '#name' => 'change_image',
      '#limit_validation_errors' => [$element['#parents']],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    // Setup the image search.
    $element['search'] = [
      '#type' => 'details',
      '#title' => t('Find an image on SmugMug'),
      '#description' => t('Enter search arguments to find an image in the @smugmug_user account of SmugMug.', [
        '@smugmug_user' => $smugmug_ocha['Name'],
      ]),
      '#open' => TRUE,
      '#states' => $states,
      '#attributes' => [
        'class' => [!$editing ? 'visually-hidden' : NULL],
      ],
    ];
    $element['search']['text'] = [
      '#type' => 'textfield',
      '#title' => t('Text'),
      '#default_value' => $values['search']['text'] ?? '',
    ];
    $element['search']['keywords'] = [
      '#type' => 'textfield',
      '#title' => t('Keywords'),
      '#default_value' => $values['search']['keywords'] ?? '',
    ];
    $element['search']['apply'] = [
      '#type' => 'submit',
      '#value' => t('Apply'),
      '#name' => 'apply',
      '#limit_validation_errors' => [array_merge($element['#parents'], ['search'])],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $search_args = array_filter([
      'Text' => $values['search']['text'] ?? NULL,
      'Keywords' => $values['search']['keywords'] ?? NULL,
    ]);

    $file_options = [];
    $images = $image_search->searchImages([
      'Scope' => $smugmug_ocha['Uri'],
    ] + $search_args);
    foreach ($images as $image) {
      $image_key = $image['ImageKey'];
      $serial = $image['Serial'];
      $image_id = $image_key . '-' . $serial;
      $file_options[$image_id] = [
        'preview' => [
          'data' => [
            '#theme' => 'imagecache_external',
            '#style_name' => 'thumbnail',
            '#uri' => $image['ThumbnailUrl'],
          ],
        ],
        'caption' => [
          'data' => [
            '#markup' => (!empty($image['Title']) ? new FormattableMarkup('<strong>@title</strong><br />', ['@title' => $image['Title']]) : NULL) . $image['Caption'],
          ],
        ],
        'date' => $date_formatter->format(strtotime($image['Date']), 'custom', 'd M Y'),
      ];
    }

    $empty_text = t('No images found.') . (!empty($search_args) ? ' ' . t('Please revise your search arguments.') : NULL);
    $element['search']['image_id'] = [
      '#type' => 'tableselect',
      '#tree' => TRUE,
      '#header' => $table_header,
      '#validated' => TRUE,
      '#options' => $file_options,
      '#default_value' => $values['image_id'] ?? array_key_first($file_options),
      '#multiple' => FALSE,
      '#empty' => $empty_text,
      '#required' => FALSE,
      '#states' => $states,
    ];
    $element['search']['select_image'] = [
      '#type' => 'submit',
      '#value' => t('Select this image'),
      '#name' => 'select_image',
      '#limit_validation_errors' => [array_merge($element['#parents'], ['search'])],
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    $element['search']['select_cancel'] = [
      '#type' => 'submit',
      '#value' => t('Cancel'),
      '#name' => 'select_cancel',
      '#ajax' => [
        'event' => 'click',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderSmugmugImage(array $element) {
    $element['#attributes']['type'] = 'smugmug_image';
    Element::setAttributes($element, ['id', 'name', 'value']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-smugmug-image']);
    return $element;
  }

  /**
   * Get the SmugMug image search service.
   *
   * @return \Drupal\smugmug_api\Service\ImageSearch
   *   The SmugMug image search service.
   */
  private static function getSmugmugImageSearchService() {
    try {
      return \Drupal::service('smugmug_api.image_search');
    }
    catch (\Exception $e) {
      // Fail silently.
    }
  }

  /**
   * Get the SmugMug image service.
   *
   * @return \Drupal\smugmug_api\Service\Image
   *   The SmugMug image service.
   */
  private static function getSmugmugImageService() {
    try {
      return \Drupal::service('smugmug_api.image');
    }
    catch (\Exception $e) {
      // Fail silently.
    }
  }

  /**
   * Get the date formatter service.
   *
   * @return \Drupal\Core\Datetime\DateFormatterInterface
   *   The date formatter service.
   */
  private static function getDateFormatter() {
    return \Drupal::service('date.formatter');
  }

}
