<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\Traits\RemoteElementTrait;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Provides an autocomplete form element for remote documents.
 *
 * Properties:
 * - #default_value: (optional) The default entity or an array of default
 *   entities.
 * - #process_default_value: (optional) Set to FALSE if the #default_value
 *   property is processed and access checked elsewhere (such as by a Field API
 *   widget). Defaults to TRUE.
 *
 * Usage example:
 * @code
 * $form['my_element'] = [
 *  '#type' => 'remote_document',
 *  '#default_value' => $document,
 * ];
 * @endcode
 *
 * @FormElement("remote_document")
 */
class RemoteDocument extends FormElement {

  use AjaxElementTrait;
  use RemoteElementTrait;

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
        [$class, 'processRemoteDocument'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme_wrappers' => ['form_element'],
      '#required' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if (empty($input) && isset($element['#default_value'])) {
      if ($element['#default_value']) {
        if (!is_object($element['#default_value']) || !$element['#default_value'] instanceof RemoteDocumentInterface) {
          throw new \InvalidArgumentException('The #default_value property has to be an instance of \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface.');
        }

        /** @var \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface $document */
        $document = $element['#default_value'];
        return [
          'remote_source' => $document->getSource()->getPluginId(),
          'document_id' => $document->getId(),
        ];
      }
    }

    if ($input !== FALSE) {
      return $input;
    }
  }

  /**
   * Adds entity autocomplete functionality to a form element.
   *
   * @param array $element
   *   The form element to process. Properties used:
   *   - #remote_source: The ID of a remote source.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The form element.
   *
   * @throws \InvalidArgumentException
   *   Exception thrown when the #target_type or #autocreate['bundle'] are
   *   missing.
   */
  public static function processRemoteDocument(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $remote_source_options = self::getRemoteSourceOptions();

    $values = $form_state->getValue($element['#parents']);
    $remote_source_key = $values['remote_source'] ?? array_key_first($remote_source_options);
    $form_state->set('remote_source', $remote_source_key);
    $form_state->setValue('document_id', NULL);

    $element['remote_source'] = [
      '#type' => 'remote_source',
      '#title' => t('Remote source'),
      '#default_value' => $remote_source_key,
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
      '#required' => $element['#required'],
    ];

    if ($remote_source_key && $remote_source = self::getRemoteSourceInstance($remote_source_key)) {
      // Select the remote document.
      $element['document_id'] = [
        '#type' => 'remote_document_autocomplete',
        '#title' => t('Document'),
        '#remote_source' => $remote_source->getPluginId(),
        '#description' => t('Type the title of a document to see suggestions.'),
        '#default_value' => !empty($values['document_id']) ? $remote_source->getDocument($values['document_id']) : NULL,
        '#required' => $element['#required'],
      ];
    }
    else {
      $element['#disabled'] = TRUE;
    }
    return $element;
  }

}
