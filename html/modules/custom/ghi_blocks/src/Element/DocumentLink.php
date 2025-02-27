<?php

namespace Drupal\ghi_blocks\Element;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Traits\VerticalTabsTrait;
use Drupal\link\Plugin\Field\FieldWidget\LinkWidget;

/**
 * Provides a document link element with meta data and multiple languages.
 */
#[FormElement('document_link')]
class DocumentLink extends FormElementBase {

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
      '#date' => TRUE,
      // Whether to impose a unique file type for the different language
      // specific document links.
      '#unique_filetype' => FALSE,
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
    $form_state->set('file_types', []);

    // Define hidden table fields. These are fields that are filled in
    // automatically by the validate handler once the url has been
    // analysed.
    $hidden_fields = [
      'filetype',
      'mimetype',
      'filesize',
    ];

    $element['date'] = [
      '#type' => 'date',
      '#title' => t('Date'),
      '#default_value' => $element['#default_value']['date'] ?? NULL,
      '#access' => $element['#date'],
    ];

    $caption_lines = [t('Enter one link target per supported language. You can also temporarily disable some links.')];
    if (!empty($element['#unique_filetype'])) {
      $caption_lines[] = t('All urls must reference resources of the same file type.');
    }

    $element['table_caption'] = [
      '#type' => 'markup',
      '#markup' => Markup::create('<p>' . implode('<br />', $caption_lines) . '</p>'),
    ];

    $element['file_details'] = [
      '#type' => 'table',
      '#title' => t('Language specific documents links'),
      '#header' => [
        t('Language'),
        t('Url'),
        [
          'data' => t('Disabled'),
          'colspan' => count($hidden_fields) + 1,
        ],
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
        '#title' => t('URL for @language', [
          '@language' => $label,
        ]),
        '#title_display' => 'invisible',
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
      foreach ($hidden_fields as $hidden_field) {
        $element['file_details'][$key][$hidden_field] = [
          '#type' => 'hidden',
          '#default_value' => $details[$hidden_field] ?? NULL,
          '#wrapper_attributes' => [
            'class' => 'hidden',
          ],
        ];
      }
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
      $form_state->setError($element, t('Failed to retrieve information for the entered <em>URL</em>.'));
      return;
    }

    $content_type = $response->getHeader(('Content-Type')) ?? [];
    $content_type = reset($content_type);
    $content_length = $response->getHeader('Content-Length') ?? [];
    $content_length = reset($content_length);

    if (empty($content_type) || empty($content_length)) {
      $form_state->setError($element, t('The entered <em>URL</em> does not seem to reference a valid resource.'));
      return;
    }

    $is_webpage = strpos($content_type, 'text/html') !== FALSE;
    if ($is_webpage) {
      $file_type = 'html';
      $mime_type = 'text/html';
    }
    else {
      $filename = $target_url;
      $mime_type = $content_type;
      $mime_type_parts = explode('/', $mime_type);
      $file_type = end($mime_type_parts);
      if (strlen($file_type) > 4 || strpos($file_type, '.')) {
        // Prevent file types like
        // vnd.openxmlformats-officedocument.spreadsheetml.sheet.
        $file_type = pathinfo($filename, PATHINFO_EXTENSION);
      }
    }

    if (!$file_type || !$mime_type) {
      $form_state->setError($element, t('The entered <em>URL</em> does not seem to represent a valid document.'));
    }

    // Check if all files in this document group have the same type.
    $array_parents = $element['#array_parents'];
    array_pop($array_parents);
    array_pop($array_parents);
    array_pop($array_parents);
    $parent_element = NestedArray::getValue($form, $array_parents);
    if (!empty($parent_element['#unique_filetype'])) {
      $file_ext_array = $form_state->get('file_types');
      if (!empty($file_ext_array) && !in_array($file_type, $file_ext_array)) {
        $form_state->setError($element, t('All entered values for <em>URL</em> must reference resources of the same type.'));
      }
      $file_ext_array[] = $file_type;
      $form_state->set('file_types', $file_ext_array);
    }

    $file_detail_parents = $element['#parents'];
    array_pop($file_detail_parents);
    $form_state->setValue(array_merge($file_detail_parents, ['filetype']), $file_type);
    $form_state->setValue(array_merge($file_detail_parents, ['mimetype']), $mime_type);
    $form_state->setValue(array_merge($file_detail_parents, ['filesize']), $content_length);
  }

}
