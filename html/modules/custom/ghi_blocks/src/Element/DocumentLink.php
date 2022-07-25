<?php

namespace Drupal\ghi_blocks\Element;

use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a document link element with meta data and multiple languages.
 *
 * @FormElement("document_link")
 */
class DocumentLink extends FormElement {

  use VerticalTabsTrait;

  const LANGUAGES = [
    'English' => 'English',
    'Français' => 'Français',
    'Español' => 'Español',
    'Russian' => 'Russian',
    'Ukrainian' => 'Ukrainian',
    'العربية' => 'العربية',
    '普通话' => '普通话',
  ];

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
        [$class, 'processDocumentLink'],
      ],
      '#pre_render' => [
        [$class, 'preRenderDocumentLink'],
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
   * Process the document link form element.
   *
   * This is called during form build. Note that it is not possible to store
   * any arbitrary data inside the form_state object.
   */
  public static function processDocumentLink(array &$element, FormStateInterface $form_state) {

    $element['date'] = [
      '#type' => 'date',
      '#title' => t('Date'),
      '#default_value' => $element['#default_value']['date'] ?? NULL,
    ];

    $element['table_caption'] = [
      '#type' => 'markup',
      '#markup' => t('Enter one link target per supported language. You can also temporarily disable some links.'),
    ];

    $element['file_details'] = [
      '#type' => 'table',
      '#title' => t('Language specific documents links'),
      '#header' => [
        t('Language'),
        t('Url'),
        t('Disabled'),
      ],
    ];

    foreach (self::LANGUAGES as $key => $label) {
      $details = $element['#default_value']['file_details'][$key] ?? NULL;
      $element['file_details'][$key] = [];
      $element['file_details'][$key]['language'] = [
        '#markup' => $label,
      ];
      $element['file_details'][$key]['target_url'] = [
        '#type' => 'textfield',
        '#title' => t('Target URL for @language', [
          '@language' => $label,
        ]),
        '#title_display' => 'invisible',
        // '#description' => t('Specify where the document is located'),
        '#default_value' => $details['target_url'] ?? NULL,
        '#maxlength' => 512,
        '#element_validate' => [
          [LinkWidget::class, 'validateUriElement'],
          [static::class, 'validateLink'],
        ],
      ];
      $element['file_details'][$key]['disabled'] = [
        '#type' => 'checkbox',
        '#title' => t('Disabled'),
        '#title_display' => 'invisible',
        '#default_value' => $details['disabled'] ?? NULL,
      ];
    }
    return $element;
  }

  /**
   * Prerender callback.
   */
  public static function preRenderDocumentLink(array $element) {
    $element['#attributes']['type'] = 'document_link';
    Element::setAttributes($element, ['id', 'name']);
    // Sets the necessary attributes, such as the error class for validation.
    // Without this line the field will not be hightlighted, if an error
    // occurred.
    static::setAttributes($element, ['form-document-link']);
    return $element;
  }

  /**
   * Form element validation handler for the language links.
   *
   * Disallows saving inaccessible or unsupported URLs.
   */
  public static function validateLink($element, FormStateInterface $form_state, $form) {
    $target_url = $element['#value'];
    if (empty($target_url)) {
      return;
    }

    // Check if the file target url is valid.
    $response = NULL;
    try {
      /** @var \GuzzleHttp\Client $http_client */
      $http_client = \Drupal::service('http_client');
      $response = $http_client->head($target_url, ['stream' => TRUE]);
    }
    catch (\Exception $e) {
      // Just fail silently.
    }
    if (!$response || $response->getStatusCode() !== 200) {
      $form_state->setError($element, t('Failed to retrieve file information for <em>Target URL</em>.'));
      return;
    }
    $content_length = $response->getHeader('Content-Length') ?? NULL;
    $content_type = $response->getHeader(('Content-Type')) ?? [];

    $file_size = reset($content_length);
    if (empty($file_size)) {
      $form_state->setError($element, t('The <em>Target URL</em> field does not seem to contain a valid reference.'));
    }
    else {
      $filename = $target_url;
      $ext = pathinfo($filename, PATHINFO_EXTENSION);
      $mime_type = reset($content_type);
      $file_type = end(explode('/', $mime_type));
      if (strlen($file_type) > 4 || strpos($file_type, '.')) {
        // Prevent file types like
        // vnd.openxmlformats-officedocument.spreadsheetml.sheet.
        $file_type = $ext;
      }

      // Check that all files in this document group have the same type.
      if (!empty($file_ext_array) && !in_array($file_type, $file_ext_array)) {
        $form_state->setError($element, t('The <em>Target URL</em> must use the same file type for all the Document details.'));
      }

      if (!$ext || !$mime_type) {
        $form_state->setError($element, t('The <em>Target URL</em> does not seem to represent a valid document.'));
      }

      // @codingStandardsIgnoreStart
      // $document['file_details'][$index]['filesize'] = $file_size;
      // $document['file_details'][$index]['mimetype'] = $mime_type;
      // $document['file_details'][$index]['filetype'] = $file_type;

      // $form_state->setValue([
      //   $form_state->get('current_subform'),
      //   'documents',
      //   $key,
      // ], $document);
      // @codingStandardsIgnoreEnd

      $file_ext_array[] = $file_type;
    }
  }

}
