<?php

namespace Drupal\ghi_content\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Textfield;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;

/**
 * Provides an autocomplete form element for remote articles.
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
 *  '#type' => 'remote_article_autocomplete',
 *  '#remote_source' => 'gho_ncms',
 *  '#default_value' => $article,
 * ];
 * @endcode
 *
 * @FormElement("remote_article_autocomplete")
 */
class RemoteArticleAutocomplete extends Textfield {

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

    $info['#element_validate'] = [[$class, 'validateRemoteArticleAutocomplete']];
    array_unshift($info['#process'], [
      $class,
      'processRemoteArticleAutocomplete',
    ]);

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if ($input === FALSE && isset($element['#default_value']) && $element['#process_default_value']) {
      if ($element['#default_value']) {
        if (!is_object(reset($element['#default_value']))) {
          throw new \InvalidArgumentException('The #default_value property has to be an object.');
        }

        // Extract the labels from the passed-in article objects, taking access
        // checks into account.
        return static::getRemoteArticleLabels($element['#default_value']);
      }
    }

    // Potentially the #value is set directly, so it contains the 'target_id'
    // array structure instead of a string.
    if ($input !== FALSE && is_array($input)) {
      $remote_source_instance = self::getRemoteSourceInstance($element['#remote_source']);
      return array_map(function (array $item) use ($remote_source_instance) {
        return $remote_source_instance->getArticleTitle($item['article_id']);
      }, $input);
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
  public static function processRemoteArticleAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $remote_source = $element['#remote_source'];
    if ($remote_source) {
      $element['#autocomplete_route_name'] = 'ghi_content.remote.autocomplete_article';
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
  public static function validateRemoteArticleAutocomplete(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $value = NULL;

    if (!empty($element['#value'])) {

      // GET forms might pass the validated data around on the next request, in
      // which case it will already be in the expected format.
      if (is_array($element['#value'])) {
        $value = $element['#value'];
      }
      else {
        $input_values = [$element['#value']];
        $remote_source_instance = self::getRemoteSourceInstance($element['#remote_source']);
        foreach ($input_values as $input) {
          $match = static::extractArticleIdFromAutocompleteInput($input);

          if ($match === NULL) {
            // Try to get a match from the input string when the user didn't use
            // the autocomplete but filled in a value manually.
            $match = self::matchRemoteArticleByTitle($remote_source_instance, $input, $element, $form_state);
          }

          if ($match !== NULL) {
            $value[] = [
              'article_id' => $match,
            ];
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
  protected static function matchRemoteArticleByTitle(RemoteSourceInterface $remote_source_instance, $input, array &$element, FormStateInterface $form_state) {
    $result = $remote_source_instance->searchArticlesByTitle($input);
    $articles = $result && $result->items ? $result->items : [];

    $params = [
      '%value' => $input,
      '@value' => $input,
      '@entity_type_plural' => t('Articles'),
    ];
    if (empty($articles)) {
      // Error if there are no entities available for a required field.
      $form_state->setError($element, t('There are no @entity_type_plural matching "%value".', $params));
    }
    elseif (count($articles) > 5) {
      $article = reset($articles);
      $params['@id'] = $article->id;
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many @entity_type_plural are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($articles) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = [];
      foreach ($articles as $id => $article) {
        $multiples[] = $article->title . ' (' . $article->id . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple @entity_type_plural match this reference; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => strip_tags(implode('", "', $multiples))] + $params));
    }
    else {
      // Take the one and only matching entity.
      $article = reset($articles);
      return $article->id;
    }
  }

  /**
   * Converts an array of article objects into a string of article labels.
   *
   * @param object[] $articles
   *   An array of article objects.
   *
   * @return string
   *   A string of entity labels separated by commas.
   */
  public static function getRemoteArticleLabels(array $articles) {

    $article_labels = [];
    foreach ($articles as $article) {

      // Use the special view label, since some entities allow the label to be
      // viewed, even if the entity is not allowed to be viewed.
      $label = $article->title;

      // Labels containing commas or quotes must be wrapped in quotes.
      $article_labels[] = Tags::encode($label);
    }

    return implode(', ', $article_labels);
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
  public static function extractArticleIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label (article id)', match the ID from inside the parentheses.
    // @todo Add support for articles containing parentheses in their ID.
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
