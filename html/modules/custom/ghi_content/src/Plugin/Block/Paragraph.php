<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\gho_footnotes\GhoFootnotes;

/**
 * Provides a 'Paragraph' block.
 *
 * @Block(
 *  id = "paragraph",
 *  admin_label = @Translation("Paragraph"),
 *  category = @Translation("Narrative Content"),
 *  config_forms = {
 *    "article_select" = {
 *      "title" = @Translation("Article selection"),
 *      "callback" = "articleSelectForm",
 *      "base_form" = TRUE
 *    },
 *    "paragraph" = {
 *      "title" = @Translation("Paragraph"),
 *      "callback" = "paragraphForm"
 *    }
 *  }
 * )
 */
class Paragraph extends ContentBlockBase implements AutomaticTitleBlockInterface, MultiStepFormBlockInterface, TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    $conf = $this->getBlockConfig();
    return $conf['paragraph']['title'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewFallbackString() {
    $paragraph = $this->getParagraph();
    if (!$paragraph) {
      return parent::getPreviewFallbackString();
    }
    return $this->t('"@block: @paragraph_type" block', [
      '@block' => $this->label(),
      '@paragraph_type' => $paragraph->getTypeLabel(),
    ]);
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
    return $this->buildParagraph($paragraph, $conf['paragraph']['title'], $this->isPreview());
  }

  /**
   * Build the render array for a paragraph.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $paragraph
   *   The remote paragraph object.
   * @param string|null $title
   *   A title for the paragraph or NULL.
   * @param bool $preview
   *   Whether this is a preview of the paragraph or not. The main reason this
   *   is needed is for proper footnote support.
   *
   * @return array
   *   The full render array for this paragraph block.
   */
  private function buildParagraph(RemoteParagraphInterface $paragraph, $title = NULL, $preview = FALSE) {

    // Add GHO specific theme components.
    $theme_components = $this->getThemeComponents($paragraph);

    // Get the rendered paragraph.
    $rendered = $paragraph->getRendered();

    // Move gho specific paragraph classes to the block wrapper attributes, so
    // that CSS logic that targets subsequent elements can be applied.
    $wrapper_attributes = [];
    $block_attributes = [];
    $dom = Html::load($rendered);
    if ($paragraph->getType() == 'sub_article') {
      // Remove the footer from sub articles.
      foreach (iterator_to_array($dom->getElementsByTagName('footer')) as $footer) {
        $footer->parentNode->removeChild($footer);
      }
    }
    $child = $dom->getElementsByTagName('div')->item(0);
    if ($child) {
      $attributes = $child->attributes;
      if ($attributes && $attributes->getNamedItem('class') && $attributes->getNamedItem('class')->nodeValue) {
        $class_attribute = $attributes->getNamedItem('class')->nodeValue;
        $classes = explode(' ', $class_attribute);
        $gho_classes = !empty($classes) ? array_filter($classes, function ($class) {
          return strpos($class, 'gho-') === 0;
        }) : [];
        $block_attributes['class'] = array_map(function ($class) {
          return $this->getPluginId() . '--' . $class;
        }, $gho_classes);
        $wrapper_attributes['class'] = $gho_classes;
        $attributes->getNamedItem('class')->nodeValue = implode(' ', array_diff($classes, $gho_classes));
        $rendered = trim(Html::serialize($dom));
      }
    }

    $build = [
      '#type' => 'container',
      '#title' => $title,
      '#attributes' => [
        'data-paragraph-id' => $paragraph->getId(),
      ] + $block_attributes,
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
      if ($preview) {
        $build['content']['#post_render'][] = [
          static::class,
          'preparePreviewParagraph',
        ];
      }
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
    if ($paragraph->getType() == 'sub_article') {
      // Find the paragraph types used in the sub article and add the
      // components for those too.
      $matches = [];
      preg_match_all('/paragraph--type--((\w|-)*)/', $paragraph->getRendered(), $matches);
      $types = !empty($matches[1]) ? array_unique($matches[1]) : [];
      foreach ($types as $type) {
        $theme_components[] = 'common_design_subtheme/gho-' . $type;
        if ($type == 'bottom-figure-row') {
          $theme_components[] = 'common_design_subtheme/gho-needs-and-requirements';
        }
      }
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
  public function getDefaultSubform($is_new = FALSE) {
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
      return $this->getArticle() && $this->getArticle() instanceof RemoteArticleInterface;
    }
    return !$this->lockArticle();
  }

  /**
   * Select article form.
   */
  public function articleSelectForm(array $form, FormStateInterface $form_state) {
    $wrapper_id = Html::getId('form-wrapper-ghi-block-config');

    $form['article'] = [
      '#type' => 'remote_article',
      '#default_value' => $this->getArticle(),
      '#required' => TRUE,
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
      '#title' => $this->lockArticle() ? $this->t('Article (locked)') : $this->t('Selected article'),
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
      // We need to fully prerender the paragraph so that things like footnotes
      // are handled correctly.
      $build = $this->buildParagraph($_paragraph, NULL, TRUE);
      $options[$_paragraph->getId()] = $this->renderer->render($build);
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
        'library' => array_unique($theme_components),
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
  public function getArticle() {
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
    // Make sure wa are always having an array, as this is the way that the
    // markup_select form element provides it's value.
    $paragraph_id = (array) $conf['paragraph']['paragraph_id'];
    return $article->getParagraph(reset($paragraph_id));
  }

  /**
   * Check if the article is locked for this paragraph element.
   *
   * The article for a paragraph is locked if there is an article set and if
   * additionally the lock_article flag is set in the configuration.
   *
   * @return bool
   *   TRUE if the article is locked, FALSE otherwise.
   */
  public function lockArticle() {
    return $this->getArticle() && !empty($this->configuration['lock_article']);
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'preparePreviewParagraph',
    ];
  }

  /**
   * Post render function to prepare a paragraph for display.
   *
   * In preview, we need to call GhoFootnotes::updateFootnotes, so that
   * footnotes inside each paragraph are processed inidividually, thus turning
   * the custom gho footnote markup into little jump links, assuring a frontend
   * rendering that equals the output on a fully rendered page.
   * We also need to wrap that into logic for temporarily disabling theme
   * debug, because the file suggestion HTML comments might create file
   * suggestions containing double-hyphens, which are not allowed in XML
   * comments and prevent the dom related logic in
   * GhoFootnotes::updateFootnotes from working correctly.
   *
   * In GHI the problem is introduced by the Gin Layout Builder module, which
   * alters file suggestions in gin_lb_theme_suggestions_alter() for some
   * routes, particularily the block remove route.
   *
   * We could have made modifications directly in GhoFootnotes, but because the
   * gho_footnotes module is a straight copy from the GHO project, it's better
   * to handle this here and leave the module untouched so that we can easier
   * keep both versions in sync.
   */
  public static function preparePreviewParagraph($html, $build) {
    /** @var \Twig\Environment $twig_service */
    $twig_service = \Drupal::service('twig');
    $twig_debug = $twig_service->isDebug();
    if ($twig_debug) {
      $twig_service->disableDebug();
    }
    $html = GhoFootnotes::updateFootnotes($html, $build);
    if ($twig_debug) {
      $twig_service->enableDebug();
    }
    return $html;
  }

}
