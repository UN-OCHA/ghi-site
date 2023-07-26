<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides an autocomplete form element for remote documents.
 *
 * The autocomplete form element allows users to select one or multiple
 * entities, which can come from all or specific bundles of an entity type.
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
 *  '#type' => 'remote_document_autocomplete',
 *  '#remote_source' => 'hpc_content_module',
 *  '#default_value' => $document,
 * ];
 * @endcode
 *
 * @FormElement("remote_document_autocomplete")
 */
class RemoteDocumentAutocomplete extends Textfield {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $info = parent::getInfo();
    $class = static::class;

    // IMPORTANT! This should only be set to FALSE if the #default_value
    // property is processed at another level (e.g. by a Field API widget) and
    // its value is properly checked for access.
    $info['#process_default_value'] = TRUE;

    array_unshift($info['#process'], [
      $class,
      'processRemoteDocumentAutocomplete',
    ]);

    $info['#element_validate'] = [
      [$class, 'validateRemoteDocumentAutocomplete'],
    ];
    $info['#value_callback'] = [[$class, 'valueCallback']];

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#process_default_value']) {
      if ($element['#default_value']) {
        if (!is_object($element['#default_value']) || !$element['#default_value'] instanceof RemoteDocumentInterface) {
          throw new \InvalidArgumentException('The #default_value property has to be an instance of \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface.');
        }

        /** @var \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface $document */
        $document = $element['#default_value'];
        return $document->getTitle() . ' (' . $document->getId() . ')';
      }
    }

    if ($input !== FALSE) {
      $remote_source = self::getRemoteSourceInstance($element['#remote_source']);
      // Process the input. This can be either an document id, or a value in the
      // format "title (id)".
      $document_id = $input;
      if (is_scalar($input)) {
        $document_id = self::extractDocumentIdFromAutocompleteInput($input);
        if (!$document_id) {
          $document_id = self::matchRemoteDocumentByTitle($remote_source, $input, $element, $form_state);
        }
      }
      $document = $remote_source->getDocument($document_id);
      return $document ? ($document->getTitle() . ' (' . $document_id . ')') : '';
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
  public static function processRemoteDocumentAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $remote_source = $element['#remote_source'];
    if ($remote_source) {
      $element['#autocomplete_route_name'] = 'ghi_content.remote.autocomplete_document';
      $element['#autocomplete_route_parameters'] = [
        'remote_source' => $remote_source,
      ];
    }
    else {
      $element['#disabled'] = TRUE;
    }
    return $element;
  }

  /**
   * Form element validation handler for entity_autocomplete elements.
   */
  public static function validateRemoteDocumentAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;
    if (!empty($element['#value'])) {

      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = [trim($element['#value'], ' "')];
        $remote_source_instance = self::getRemoteSourceInstance($element['#remote_source']);
        foreach ($input_values as $input) {
          $match = static::extractDocumentIdFromAutocompleteInput($input);

          if ($match === NULL) {
            // Try to get a match from the input string when the user didn't use
            // the autocomplete but filled in a value manually.
            $match = self::matchRemoteDocumentByTitle($remote_source_instance, $input, $element, $form_state);
          }

          if ($match !== NULL) {
            $value = $match;
          }
        }
      }
    }
    $form_state->setValueForElement($element, $value);
  }

  /**
   * Finds an entity from an autocomplete input without an explicit ID.
   *
   * The method will return an entity ID if one single entity unambiguously
   * matches the incoming input, and assign form errors otherwise.
   *
   * @param \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance
   *   Remote source instance.
   * @param string $input
   *   Single string from autocomplete element.
   * @param array $element
   *   The form element to set a form error.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return int|null
   *   Value of a matching entity ID, or NULL if none.
   */
  protected static function matchRemoteDocumentByTitle(RemoteSourceInterface $remote_source_instance, $input, array &$element, FormStateInterface $form_state) {
    $documents = $remote_source_instance->searchDocumentsByTitle($input);

    $exact_match = array_filter($documents, function (RemoteDocumentInterface $document) use ($input) {
      return $document->getTitle() === $input;
    });

    if ($exact_match && count($exact_match) == 1) {
      $document = reset($exact_match);
      return $document->getId();
    }

    $params = [
      '%value' => $input,
      '@value' => $input,
      '@entity_type_plural' => t('Documents'),
    ];
    if (empty($documents)) {
      // Error if there are no entities available for a required field.
      $form_state->setError($element, t('There are no @entity_type_plural matching "%value".', $params));
    }
    elseif (count($documents) > 5) {
      $document = reset($documents);
      $params['@id'] = $document->getId();
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many @entity_type_plural are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($documents) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = [];
      foreach ($documents as $id => $document) {
        $multiples[] = $document->getTitle() . ' (' . $document->getId() . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple @entity_type_plural match this reference "%value"; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => strip_tags(implode('", "', $multiples))] + $params));
    }
    else {
      // Take the one and only matching entity.
      $document = reset($documents);
      return $document->getId();
    }
  }

  /**
   * Extracts the entity ID from the autocompletion result.
   *
   * @param string $input
   *   The input coming from the autocompletion result.
   *
   * @return mixed|null
   *   An entity ID or NULL if the input does not contain one.
   */
  public static function extractDocumentIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label (document id)', match the ID from inside the parentheses.
    // @todo Add support for documents containing parentheses in their ID.
    if (preg_match("/.+\s\(([^\)]+)\)/", $input, $matches)) {
      $match = $matches[1];
    }

    return $match;
  }

  /**
   * Get a remote source instance.
   *
   * @param string $remote_source
   *   The machine name of the remote source.
   *
   * @return \Drupal\ghi_content\RemoteSource\RemoteSourceInterface
   *   The remote source instance
   */
  private static function getRemoteSourceInstance($remote_source) {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    return $remote_source_manager->createInstance($remote_source);
  }

}
