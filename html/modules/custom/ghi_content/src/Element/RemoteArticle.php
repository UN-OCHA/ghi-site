<?php

namespace Drupal\ghi_content\Element;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
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

    $remote_source_options = self::getRemoteSourceOptions();

    $values = $form_state->getValue($element['#parents']);
    $remote_source_key = $values['remote_source'] ?? array_key_first($remote_source_options);
    $form_state->set('remote_source', $remote_source_key);
    $form_state->setValue('article_id', NULL);

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
      // Select the remote article.
      $element['article_id'] = [
        '#type' => 'remote_article_autocomplete',
        '#title' => t('Article'),
        '#remote_source' => $remote_source->getPluginId(),
        '#description' => t('Type the title of an article to see suggestions.'),
        '#default_value' => !empty($values['article_id']) ? $remote_source->getArticle($values['article_id']) : NULL,
        '#required' => $element['#required'],
      ];
    }
    else {
      $element['#disabled'] = TRUE;
    }
    return $element;
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
