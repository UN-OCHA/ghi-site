<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;

/**
 * Provides a 'Paragraph' block.
 *
 * @Block(
 *  id = "paragraph",
 *  admin_label = @Translation("Paragraph"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node")),
 *   }
 * )
 */
class Paragraph extends ContentBlockBase implements MultiStepFormBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $paragraph = $this->getParagraph();
    if (!$paragraph) {
      return;
    }

    $conf = $this->getBlockConfig();

    // Add GHO specific theme components.
    $theme_components = $this->getThemeComponents($paragraph);

    // Get the rendered paragraph.
    $rendered = $paragraph->getRendered();

    // Move gho specific paragraph classes to the block wrapper attributes, so
    // that CSS logic that targets subsequent elements can be applied.
    $wrapper_attributes = [];
    $dom = Html::load($rendered);
    $child = $dom->getElementsByTagName('div')->item(0);
    $attributes = $child->attributes;
    if ($attributes && $attributes->getNamedItem('class') && $attributes->getNamedItem('class')->nodeValue) {
      $class_attribute = $attributes->getNamedItem('class')->nodeValue;
      $classes = explode(' ', $class_attribute);
      $gho_classes = !empty($classes) ? array_filter($classes, function ($class) {
        return strpos($class, 'gho-') === 0;
      }) : [];
      $wrapper_attributes['class'] = $gho_classes;
      $attributes->getNamedItem('class')->nodeValue = implode(' ', array_diff($classes, $gho_classes));
      $rendered = trim(Html::serialize($dom));
    }

    $build = [
      '#type' => 'container',
      '#title' => $conf['paragraph']['title'],
      '#attributes' => [
        'data-paragraph-id' => $paragraph->getId(),
      ],
      'content' => [
        '#type' => 'markup',
        '#markup' => Markup::create($rendered),
        '#view_mode' => 'full',
      ],
      '#wrapper_attributes' => $wrapper_attributes,
    ];

    if ($this->moduleHandler->moduleExists('gho_footnotes')) {
      // Make sure to add the gho-footnotes component.
      $theme_components[] = 'common_design_subtheme/gho-footnotes';
    }

    $build['content']['#attached'] = [
      'library' => $theme_components,
    ];

    return $build;
  }

  /**
   * Get the required theme components for the given paragraph.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $paragraph
   *   The paragraph for which theme components should be fetched.
   *
   * @return array
   *   An array of library keys.
   */
  public function getThemeComponents(RemoteParagraphInterface $paragraph) {
    $theme_components = [];
    $theme_components[] = 'common_design_subtheme/gho-' . Html::getClass($paragraph->getType());
    if ($paragraph->getType() == 'bottom_figure_row') {
      $theme_components[] = 'common_design_subtheme/gho-needs-and-requirements';
    }
    return $theme_components;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'article_select' => [
        'article' => [
          'remote_source' => NULL,
          'article_id' => NULL,
        ],
      ],
      'paragraph' => [
        'paragraph_id' => NULL,
        'title' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getSubforms() {
    return [
      'article_select' => [
        'title' => $this->t('Article selection'),
        'callback' => 'articleSelectForm',
      ],
      'paragraph' => [
        'title' => $this->t('Paragraph'),
        'callback' => 'paragraphForm',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform() {
    if (!$this->getArticle()) {
      return 'article_select';
    }
    return 'paragraph';
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform($form, FormStateInterface $form_state, $subform_key) {
    if ($subform_key == 'paragraph') {
      return $this->getArticle() instanceof RemoteArticleInterface;
    }
    return TRUE;
  }

  /**
   * Select article form.
   */
  public function articleSelectForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');

    $form['article'] = [
      '#type' => 'remote_article',
      '#default_value' => $this->getArticle(),
    ];

    $form['select_article'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select this article'),
      '#element_submit' => [get_class($this) . '::articleSelectSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'paragraph',
    ];

    return $form;
  }

  /**
   * Submit callback for the "Select article" button.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response.
   */
  public static function articleSelectSubmit(array $form, FormStateInterface $form_state) {
    return self::ajaxMultiStepSubmit($form, $form_state);
  }

  /**
   * Paragraph config form.
   */
  public function paragraphForm(array $form, FormStateInterface $form_state) {
    $conf = $this->getBlockConfig();

    $article = $this->getArticle();
    $paragraph = $this->getParagraph();
    $form['article_summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Selected article'),
      '#markup' => $article->getSource()->getPluginLabel() . ': ' . $article->getTitle(),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#description' => $this->t('Give this paragraph an optional title.'),
      '#default_value' => !empty($conf['paragraph']['title']) ? $conf['paragraph']['title'] : NULL,
    ];

    $options = [];
    $theme_components = [];
    foreach ($article->getParagraphs() as $_paragraph) {
      $options[$_paragraph->getId()] = $_paragraph->getRendered();
      $theme_components += array_merge($theme_components, $this->getThemeComponents($_paragraph));
    }

    $form['paragraph_id'] = [
      '#type' => 'markup_select',
      '#title' => $this->t('Paragraph'),
      '#description' => $this->t('Select a paragraph from the article.'),
      '#options' => $options,
      '#limit' => 1,
      '#cols' => 3,
      '#default_value' => $paragraph ? $paragraph->getId() : NULL,
      '#attached' => [
        'library' => $theme_components,
      ],
    ];

    return $form;
  }

  /**
   * Get the configured article.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteArticleInterface
   *   The remote article.
   */
  protected function getArticle() {
    $conf = $this->getBlockConfig();
    $remote_source_key = $conf['article_select']['article']['remote_source'] ?? NULL;
    if (!$remote_source_key) {
      return NULL;
    }
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $remote_source = $remote_source_manager->createInstance($remote_source_key);
    $article_id = $conf['article_select']['article']['article_id'] ?? NULL;
    if (!$remote_source || !$article_id) {
      return NULL;
    }
    return $remote_source->getArticle($article_id);
  }

  /**
   * Get the configured paragraph from the article.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface|null
   *   A paragraph object as retrieved from the article.
   */
  private function getParagraph() {
    $article = $this->getArticle();
    $conf = $this->getBlockConfig();
    if (!$article || empty($conf['paragraph']['paragraph_id'])) {
      return;
    }
    return $article->getParagraph(reset($conf['paragraph']['paragraph_id']));
  }

}
