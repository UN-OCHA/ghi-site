<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\Textfield;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\ghi_content\Traits\RemoteElementTrait;

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
 *  '#remote_source' => 'hpc_content_module',
 *  '#default_value' => $article,
 * ];
 * @endcode
 */
#[FormElement('remote_article_autocomplete')]
class RemoteArticleAutocomplete extends Textfield {

  use RemoteElementTrait;

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
      'processRemoteArticleAutocomplete',
    ]);

    $info['#element_validate'] = [[$class, 'validateRemoteArticleAutocomplete']];
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
        if (!is_object($element['#default_value']) || !$element['#default_value'] instanceof RemoteArticleInterface) {
          throw new \InvalidArgumentException('The #default_value property has to be an instance of \Drupal\ghi_content\RemoteContent\RemoteArticleInterface.');
        }

        /** @var \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article */
        $article = $element['#default_value'];
        return $article->getTitle() . ' (' . $article->getId() . ')';
      }
    }

    if ($input !== FALSE) {
      $remote_source = self::getRemoteSourceInstance($element['#remote_source']);
      // Process the input. This can be either an article id, or a value in the
      // format "title (id)".
      $article_id = $input;
      if (is_scalar($input)) {
        $article_id = self::extractArticleIdFromAutocompleteInput($input);
        if (!$article_id) {
          $article_id = self::matchRemoteArticleByTitle($remote_source, $input, $element, $form_state);
        }
      }
      $article = $remote_source->getArticle($article_id);
      return $article ? ($article->getTitle() . ' (' . $article_id . ')') : '';
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
        $input_values = [trim($element['#value'], ' "')];
        $remote_source_instance = self::getRemoteSourceInstance($element['#remote_source']);
        foreach ($input_values as $input) {
          $match = static::extractArticleIdFromAutocompleteInput($input);

          if ($match === NULL) {
            // Try to get a match from the input string when the user didn't use
            // the autocomplete but filled in a value manually.
            $match = self::matchRemoteArticleByTitle($remote_source_instance, $input, $element, $form_state);
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
  protected static function matchRemoteArticleByTitle(RemoteSourceInterface $remote_source_instance, $input, array &$element, FormStateInterface $form_state) {
    $articles = $remote_source_instance->searchArticlesByTitle($input);

    $exact_match = array_filter($articles, function (RemoteArticleInterface $article) use ($input) {
      return $article->getTitle() === $input;
    });

    if ($exact_match && count($exact_match) == 1) {
      $article = reset($exact_match);
      return $article->getId();
    }

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
      $params['@id'] = $article->getId();
      // Error if there are more than 5 matching entities.
      $form_state->setError($element, t('Many @entity_type_plural are called %value. Specify the one you want by appending the id in parentheses, like "@value (@id)".', $params));
    }
    elseif (count($articles) > 1) {
      // More helpful error if there are only a few matching entities.
      $multiples = [];
      foreach ($articles as $id => $article) {
        $multiples[] = $article->getTitle() . ' (' . $article->getId() . ')';
      }
      $params['@id'] = $id;
      $form_state->setError($element, t('Multiple @entity_type_plural match this reference "%value"; "%multiple". Specify the one you want by appending the id in parentheses, like "@value (@id)".', ['%multiple' => strip_tags(implode('", "', $multiples))] + $params));
    }
    else {
      // Take the one and only matching entity.
      $article = reset($articles);
      return $article->getId();
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
  public static function extractArticleIdFromAutocompleteInput($input) {
    $match = NULL;

    // Take "label (article id)', match the ID from inside the parentheses.
    // @todo Add support for articles containing parentheses in their ID.
    if (preg_match("/.+\s\(([^\)]+)\)/", $input, $matches)) {
      $match = $matches[1];
    }

    return $match;
  }

}
