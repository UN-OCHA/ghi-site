<?php

namespace Drupal\ghi_content\Element;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\ghi_form_elements\Traits\AjaxElementTrait;

/**
 * Provides an autocomplete form element for remote articles.
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
 *  '#type' => 'remote_article',
 *  '#default_value' => $article,
 * ];
 * @endcode
 *
 * @FormElement("remote_article")
 */
class RemoteArticle extends FormElement {

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
        [$class, 'processRemoteArticle'],
        [$class, 'processAjaxForm'],
        [$class, 'processGroup'],
      ],
      '#pre_render' => [
        [$class, 'preRenderGroup'],
      ],
      '#theme_wrappers' => ['form_element'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    // Process the #default_value property.
    if (empty($input) && isset($element['#default_value'])) {
      if ($element['#default_value']) {
        if (!is_object($element['#default_value']) || !$element['#default_value'] instanceof RemoteArticleInterface) {
          throw new \InvalidArgumentException('The #default_value property has to be an instance of \Drupal\ghi_content\RemoteContent\RemoteArticleInterface.');
        }

        /** @var \Drupal\ghi_content\RemoteContent\RemoteArticleInterface $article */
        $article = $element['#default_value'];
        return [
          'remote_source' => $article->getSource()->getPluginId(),
          'article_id' => $article->getId(),
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
  public static function processRemoteArticle(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $wrapper_id = self::getWrapperId($element);
    $element['#prefix'] = '<div id="' . $wrapper_id . '">';
    $element['#suffix'] = '</div>';

    $values = $form_state->getValue($element['#parents']);
    $submitted_remote_source = $values['remote_source'] ?? NULL;
    $form_state->set('remote_source', $submitted_remote_source);
    $form_state->setValue('article_id', NULL);

    $remote_source_options = self::getRemoteSourceOptions();
    $element['remote_source'] = [
      '#type' => 'remote_source',
      '#title' => t('Remote source'),
      '#ajax' => [
        'event' => 'change',
        'callback' => [static::class, 'updateAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];

    $remote_source_key = $values['remote_source'] ?? array_key_first($remote_source_options);

    if ($remote_source_key && $remote_source = self::getRemoteSourceInstance($remote_source_key)) {
      // Select the remote article.
      $element['article_id'] = [
        '#type' => 'remote_article_autocomplete',
        '#title' => t('Article'),
        '#remote_source' => $remote_source->getPluginId(),
        '#description' => t('Type the title of an article to see suggestions.'),
        '#default_value' => $values['article_id'] ? $remote_source->getArticle($values['article_id']) : NULL,
      ];
    }
    else {
      $element['#disabled'] = TRUE;
    }
    return $element;
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
   * Converts an array of article objects into a string of article labels.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteArticleInterface[] $articles
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
      $label = $article->getTitle();

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

  /**
   * Get a remote source instance.
   *
   * @return string[]
   *   The remote source options
   */
  private static function getRemoteSourceOptions() {
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $definitions = $remote_source_manager->getDefinitions();
    if (empty($definitions)) {
      return [];
    }
    return array_map(function ($item) {
      return $item['label'];
    }, $definitions);
  }

}
