<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OptionalTitleBlockInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\ghi_form_elements\Traits\CustomLinkTrait;
use Drupal\gho_footnotes\GhoFootnotes;
use Drupal\hpc_common\Helpers\ThemeHelper;

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
class Paragraph extends ContentBlockBase implements OptionalTitleBlockInterface, MultiStepFormBlockInterface, TrustedCallbackInterface {

  use CustomLinkTrait;

  /**
   * The CSS class used for promoted paragraphs. This comes from the NCMS.
   */
  const PROMOTED_CLASS = 'gho-paragraph-promoted';

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'paragraph';
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
    return $this->buildParagraph($paragraph, $this->label(), $this->isPreview());
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
   * @param bool $internal_preview
   *   Whether this is an internal preview for the paragraph selection.
   *
   * @return array
   *   The full render array for this paragraph block.
   */
  private function buildParagraph(RemoteParagraphInterface $paragraph, $title = NULL, $preview = FALSE, $internal_preview = FALSE) {

    // Add GHO specific theme components.
    $theme_components = $this->getThemeComponents($paragraph);

    // Get the rendered paragraph.
    $rendered = $paragraph->getRendered();

    $full_width_paragraph_types = [
      'story',
    ];

    // Move gho specific paragraph classes to the block wrapper attributes, so
    // that CSS logic that targets subsequent elements can be applied.
    $wrapper_attributes = ['class' => []];
    $block_attributes = ['class' => []];
    $dom = Html::load($rendered);

    // See if there are links to be replaced.
    $links = $dom->getElementsByTagName('a') ?? [];
    if (!empty($links)) {
      $link_map = $paragraph->getSource()->getLinkMap($paragraph);
      foreach ($links as $link) {
        $href = $link->attributes->getNamedItem('href')?->value ?? NULL;
        if (!$href) {
          continue;
        }
        if (array_key_exists($href, $link_map)) {
          $link->attributes->getNamedItem('href')->value = $link_map[$href];
        }
        elseif (strpos($href, '/') === 0) {
          $link->parentNode->removeChild($link);
        }
      }
    }

    if ($paragraph->getType() == 'sub_article') {
      $local_subarticle = $this->getLocalSubarticle($paragraph);
      if ($local_subarticle?->isProtected()) {
        $block_attributes['class'][] = 'protected';
      }
      $this->processSubarticleParagraph($paragraph, $dom);

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

        // Get the original GHO specific classes.
        $gho_classes = !empty($classes) ? array_filter($classes, function ($class) {
          return strpos($class, 'gho-') === 0;
        }) : [];

        // Set new classes specific to our system.
        $block_attributes['class'] += array_map(function ($class) {
          return $this->getPluginId() . '--' . $class;
        }, $gho_classes);

        // Special logic for bottom figure rows to assure that styles are also
        // applied during preview.
        if (($preview || $internal_preview) && in_array('gho-bottom-figure-row', $classes)) {
          $block_attributes['class'][] = 'gho-bottom-figure-row';
        }
        // Special logic for top figure rows to assure that styles are also
        // applied during preview.
        if (($preview || $internal_preview) && in_array('gho-top-figures', $classes)) {
          $block_attributes['class'][] = 'gho-top-figures';
        }
        if (($preview || $internal_preview) && in_array('gho-top-figures--small', $classes)) {
          $block_attributes['class'][] = 'gho-top-figures--small';
        }
        if (in_array('not-collapsible', $classes)) {
          $block_attributes['class'][] = 'not-collapsible';
        }
        if (in_array('gho-top-figures--small', $classes)) {
          // Top figures (small) as standalone element has a top-border on the
          // outer container. Without this content-width class it would break
          // out of the content area, so we add that here.
          $block_attributes['class'][] = 'content-width';
        }
        if (in_array('gho-interactive-content--full-width', $gho_classes)) {
          // Remove the full width classes from the content, but don't add the
          // content-width class. This will lead to the paragraph to take as
          // much place as the hero image.
          $gho_classes = array_diff($gho_classes, ['gho-interactive-content--full-width']);
          $classes = array_diff($classes, ['gho-interactive-content--full-width']);
        }

        $wrapper_attributes['class'] += $gho_classes;
        $attributes->getNamedItem('class')->nodeValue = implode(' ', array_diff($classes, $gho_classes));
      }
    }

    // Make sure to update the rendered string.
    $rendered = trim(Html::serialize($dom));

    if ($internal_preview) {
      // Make sure we have gho specific classes available during internal
      // previews, e.g. in the paragraph selection form.
      $block_attributes['class'] = array_merge($block_attributes['class'], $gho_classes);
    }

    if (!$internal_preview && $this->isPromoted()) {
      // Mark the paragraph as being promoted if not in internal preview.
      $block_attributes['class'][] = self::PROMOTED_CLASS;
    }
    if (!$this->isPromoted()) {
      // If the paragraph is not be promoted, make sure to remove the
      // respective class from both the block classes and the wrapper.
      $block_attributes['class'] = array_diff($block_attributes['class'] ?? [], [self::PROMOTED_CLASS]);
      $wrapper_attributes['class'] = array_diff($wrapper_attributes['class'] ?? [], [self::PROMOTED_CLASS]);
    }

    // See if this paragraph should render as a full-width block.
    $full_width_paragraph = in_array($paragraph->getType(), $full_width_paragraph_types);
    $full_width = $full_width_paragraph || $this->isPromoted();

    $build = [
      '#type' => 'container',
      '#title' => $title,
      '#attributes' => [
        'data-paragraph-id' => $paragraph->getId(),
      ] + $block_attributes,
      'content' => [
        [
          '#type' => 'markup',
          '#markup' => Markup::create($rendered),
          '#view_mode' => 'full',
        ],
      ],
      '#wrapper_attributes' => $wrapper_attributes,
      '#full_width' => $full_width,
    ];

    if ($this->moduleHandler->moduleExists('gho_footnotes') && !$paragraph->getType() == 'sub_article') {
      // Make sure to add the gho-footnotes component.
      $theme_components[] = 'common_design_subtheme/gho-footnotes';
      if ($preview || $internal_preview) {
        $build['content']['#post_render'][] = [
          static::class,
          'preparePreviewParagraph',
        ];
      }
    }

    $build['content']['#attached'] = [
      'library' => $theme_components,
    ];

    if (!$internal_preview && $this->canLinkToArticlePage()) {
      $article_node = $this->getArticlePage();
      $link = $article_node->toLink($this->t('Read more'));
      $link->getUrl()->setOptions([
        'attributes' => [
          'class' => ['cd-button', 'read-more'],
        ],
      ]);
      // We need to embed the link inside a container, because we need a block
      // level element to which we can apply the content-width class.
      $build['content'][] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['content-width'],
        ],
        0 => $link->toRenderable(),
      ];
    }

    if ($this->shouldLinkToArticlePage() && $article_node = $this->getArticlePage()) {
      $build['#cache'] = [
        'tags' => $article_node->getCacheTags(),
      ];
    }

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
    if ($paragraph->getType() == 'top_figures_small') {
      // Top figures (small) can be used on it's own, but still need the main
      // component.
      $theme_components[] = 'common_design_subtheme/gho-top-figures';
    }
    if ($paragraph->getType() == 'bottom_figure_row') {
      $theme_components[] = 'common_design_subtheme/gho-needs-and-requirements';
      $theme_components[] = 'ghi_content/top_figures.tooltips';
    }
    if ($paragraph->getType() == 'top_figures' || $paragraph->getType() == 'top_figures_small') {
      $theme_components[] = 'ghi_content/top_figures.tooltips';
    }
    if ($paragraph->getPromoted() || $this->isPromoted()) {
      $theme_components[] = 'common_design_subtheme/gho-promoted-paragraph';
    }
    if ($paragraph->getType() == 'interactive_content_2_columns') {
      // Interactive content in 2 columns still needs styles.
      $theme_components[] = 'common_design_subtheme/gho-interactive-content';
    }
    if ($paragraph->getType() == 'sub_article') {
      // Find the paragraph types used in the sub article and add the
      // components for those too.
      $matches = [];
      preg_match_all('/paragraph--type--((\w|-)*)/', $paragraph->getRendered(), $matches);
      $types = !empty($matches[1]) ? array_unique($matches[1]) : [];
      foreach ($types as $type) {
        $theme_components[] = 'common_design_subtheme/gho-' . $type;
        if ($type == 'top-figures-small') {
          // Top figures (small) can be used on it's own, but still need the
          // main component.
          $theme_components[] = 'common_design_subtheme/gho-top-figures';
        }
        if ($type == 'bottom-figure-row') {
          $theme_components[] = 'common_design_subtheme/gho-needs-and-requirements';
          $theme_components[] = 'ghi_content/topfigures.tooltips';
        }
        if ($type == 'top-figures' || $type == 'top-figures-small') {
          $theme_components[] = 'ghi_content/top_figures.tooltips';
        }
      }
      $matches = [];
      preg_match_all('/gho-paragraph-promoted/', $paragraph->getRendered(), $matches);
      if (!empty($matches)) {
        $theme_components[] = 'common_design_subtheme/gho-promoted-paragraph';
      }
    }
    return array_unique($theme_components);
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
        'label' => NULL,
        'link_to_article' => FALSE,
        'promoted' => FALSE,
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
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    if ($subform_key == 'paragraph') {
      return $this->getArticle() && $this->getArticle() instanceof RemoteArticleInterface;
    }
    return !$this->lockArticle();
  }

  /**
   * Select article form.
   */
  public function articleSelectForm(array $form, FormStateInterface $form_state) {
    $form['article'] = [
      '#type' => 'remote_article',
      '#default_value' => $this->getArticle(),
      '#required' => TRUE,
    ];

    $form['select_article'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select this article'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'paragraph',
    ];

    return $form;
  }

  /**
   * Paragraph config form.
   */
  public function paragraphForm(array $form, FormStateInterface $form_state) {
    $article = $this->getArticle();
    $paragraph = $this->getParagraph();
    $form['article_summary'] = [
      '#type' => 'item',
      '#title' => $this->lockArticle() ? $this->t('Article (locked)') : $this->t('Selected article'),
      '#markup' => $article->getSource()->getPluginLabel() . ': ' . $article->getTitle(),
      '#weight' => -2,
    ];

    $form['label']['#weight'] = -1;

    $form['link_to_article'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add a link to the article page.'),
      '#description' => $this->t('The link will only be added if the article page exists and is accessible to the user.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'link_to_article'),
      '#access' => !$this->displayedOnArticlePage(),
    ];
    $form['promoted'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mark as promoted.'),
      '#description' => $this->t('Mark the paragraph as promoted, which will make it visually highlighted.'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'promoted'),
      '#access' => !$this->displayedOnArticlePage(),
    ];

    $options = [];
    $theme_components = [];
    foreach ($article->getParagraphs() as $_paragraph) {
      // We need to fully prerender the paragraph so that things like footnotes
      // are handled correctly.
      $build = $this->buildParagraph($_paragraph, NULL, TRUE, TRUE);
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
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source */
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
  public function getParagraph() {
    $article = $this->getArticle();
    $conf = $this->getBlockConfig();
    if (!$article || empty($conf['paragraph']['paragraph_id'])) {
      return;
    }
    // Make sure we have an array, as this is the way that the markup_select
    // form element provides it's value.
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
   * Get the article page associated to the configured source article.
   *
   * @return \Drupal\ghi_content\Entity\Article|null
   *   An article node object.
   */
  private function getArticlePage() {
    $article = $this->getArticle();
    if (!$article) {
      return NULL;
    }
    $article_node = $this->articleManager->loadNodeForRemoteContent($article);
    return $article_node instanceof Article ? $article_node : NULL;
  }

  /**
   * See if a paragraph block should have a "Read more" link.
   *
   * @return bool
   *   TRUE if the article page should be linked to, FALSE otherwhise.
   */
  private function shouldLinkToArticlePage() {
    $conf = $this->getBlockConfig();
    return !empty($conf['paragraph']['link_to_article']);
  }

  /**
   * See if a paragraph block can have a "Read more" link.
   *
   * This depends on configuration (whether the paragraph should have a link)
   * and the publication status of the linked article.
   *
   * @return bool
   *   TRUE if the article page can be linked to, FALSE otherwhise.
   */
  private function canLinkToArticlePage() {
    if (!$this->shouldLinkToArticlePage()) {
      return FALSE;
    }
    $article_node = $this->getArticlePage();
    if (!$article_node || !$article_node->access('view')) {
      return FALSE;
    }
    return !$this->displayedOnArticlePage();
  }

  /**
   * See if the paragraph is promoted.
   *
   * @return bool
   *   TRUE if promoted, FALSE otherwise.
   */
  private function isPromoted() {
    // Display paragraph on its article page.
    if ($this->displayedOnArticlePage()) {
      $paragraph = $this->getParagraph();
      return $paragraph && $paragraph->getPromoted();
    }
    else {
      // Outside the article page, we rely on what is configured.
      $conf = $this->getBlockConfig();
      return !empty($conf['paragraph']['promoted']);
    }
  }

  /**
   * See if the paragraph is displayed on the article page where it originates.
   *
   * @return bool
   *   TRUE if displayed on its article page, FALSE otherwise.
   */
  private function displayedOnArticlePage() {
    $page_node = $this->getPageNode();
    $article_page = $this->getArticlePage();
    if (!$page_node || !$article_page) {
      return FALSE;
    }
    return $article_page->id() == $page_node->id();
  }

  /**
   * Get the local sub-article page node for the given sub-article paragraph.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $paragraph
   *   The sub-article paragraph.
   *
   * @return \Drupal\ghi_content\Entity\Article|null
   *   An article node or NULL.
   */
  private function getLocalSubarticle(RemoteParagraphInterface $paragraph) {
    if ($paragraph->getType() != 'sub_article') {
      return NULL;
    }
    $article_id = $paragraph->getConfiguration()['article_id'] ?? NULL;
    $remote_sub_article = $article_id ? $paragraph->getSource()->getArticle($article_id) : NULL;
    if (!$remote_sub_article) {
      return NULL;
    }
    $article = $this->articleManager->loadNodeForRemoteContent($remote_sub_article);
    return $article instanceof Article ? $article : NULL;
  }

  /**
   * Process a sub-article paragraph.
   *
   * Check for protection state and access to the imported sub-article page.
   * Checks if local rendering should be used, in which case the content coming
   * from the remote system will be entirely replaced by the local rendering of
   * the imported (and potentially modified) article page that corresponds to
   * the sub-article.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteParagraphInterface $paragraph
   *   The paragraph.
   * @param \DOMDocument $dom
   *   The dom object.
   */
  private function processSubarticleParagraph(RemoteParagraphInterface $paragraph, $dom) {
    $local_subarticle = $this->getLocalSubarticle($paragraph);
    if (!$local_subarticle) {
      return;
    }

    $content_node = NULL;
    if ($local_subarticle->isProtected() && !$local_subarticle->protectedAccess()) {
      // No access to a protected article yet.
      $local_subarticle->setContextNode($this->getArticlePage()->getContextNode());
      $build = [
        '#type' => 'container',
        0 => [
          '#type' => 'html_tag',
          '#tag' => 'article',
          0 => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => [
                'content-width',
                'not-collapsible',
              ],
            ],
            0 => [
              '#markup' => $this->t('This content is currently embargoed. Please <a href="@url">click here</a> to access it.', [
                '@url' => $local_subarticle->toUrl()->toString(),
              ]),
            ],
          ],
        ],
      ];
      $html = ThemeHelper::render($build, FALSE);
      $new_dom = Html::load($html);
      $content_node = $new_dom->getElementsByTagName('article')->item(0);
    }
    elseif ($this->config('ghi_content.article_settings')->get('subarticle_local_render')) {
      // Local rendering has been requested, so we completely replace the
      // content from the remote with the rendered page content of the article
      // in our system.
      /** @var \Drupal\Core\Entity\EntityViewBuilder $view_builder */
      $view_builder = \Drupal::entityTypeManager()->getViewBuilder($local_subarticle->getEntityTypeId());
      $build = $view_builder->view($local_subarticle);
      $build['#skip_footnotes_processing'] = TRUE;
      $build = $view_builder->build($build);

      $html = ThemeHelper::render($build, FALSE);
      $html = preg_replace('/<!--(.*)-->/Uis', '', $html);

      // Create a new dom object with the local rendering result.
      $new_dom = Html::load($html);
      $content_node = $this->getElementByClass($new_dom->getElementsByTagName('article')->item(0), [
        'layout--onecol',
        'layout__region--content',
      ]);
    }

    if (!$content_node) {
      return;
    }

    $fragment = $dom->createDocumentFragment();
    $fragment->appendXML($this->renderDomNode($content_node));

    $article_content_node = $this->getElementByClass($dom->getElementsByTagName('article')->item(0), ['gho-sub-article__content']);
    if (!$article_content_node) {
      return;
    }
    while ($article_content_node->hasChildNodes()) {
      $article_content_node->removeChild($article_content_node->firstChild);
    }
    $article_content_node->appendChild($fragment);
  }

  /**
   * Find a DOM element based on the given classes.
   *
   * @param \DOMElement|\DOMNode|\DOMNameSpaceNode|null $node
   *   The dom object.
   * @param string|string[] $classes
   *   The class(es) that identify the article content. Either a single class
   *   as a string, or an array of strings. If an array of strings is given,
   *   the method will traverse the full dom tree of the given node until all
   *   classes have been found. Note there is no logic that checks the class
   *   hierarchy, so if all classes are present on the root element, this will
   *   be returned as a valid result.
   *
   * @return \DOMNode|null
   *   The dom node repesenting the article content.
   */
  private function getElementByClass($node, $classes) {
    if (!$node || !$node->childNodes) {
      return NULL;
    }
    $classes = !is_array($classes) ? [$classes] : $classes;

    if ($this->matchClasses($node, $classes) && empty($classes)) {
      return $node;
    }

    foreach ($node->childNodes->getIterator() as $child_node) {
      if ($this->matchClasses($child_node, $classes[0])) {
        array_shift($classes);
        if (empty($classes)) {
          return $child_node;
        }
      }
      $found_node = $this->getElementByClass($child_node, $classes);
      if ($found_node) {
        return $found_node;
      }
    }
  }

  /**
   * Match classes on the given dom object.
   *
   * @param \DOMElement|\DOMNode|\DOMNameSpaceNode $node
   *   The dom object.
   * @param string|string[] $class
   *   The class or classes to look for.
   *
   * @return bool
   *   TRUE if at least on of the classes has been found on the node.
   */
  private function matchClasses($node, $class) {
    $class_attribute = $node->attributes?->getNamedItem('class')?->nodeValue;
    if (!$class_attribute) {
      return FALSE;
    }
    $classes = is_array($class) ? $class : [$class];
    $found_classes = [];
    foreach ($classes as $class) {
      if (str_contains($class_attribute, $class)) {
        $found_classes[] = $class;
      }
    }
    return count($found_classes) == count($classes);
  }

  /**
   * Get the inner HTML of a DOM node.
   *
   * @param \DOMNode $node
   *   Node from which to get the innerHTML.
   *
   * @return string
   *   Inner HTML.
   */
  private function renderDomNode(\DOMNode $node) {
    // PHP DOMElement don't have a innerHTML property so this is the current way
    // of getting it.
    // @see https://bugs.php.net/bug.php?id=44762
    return implode('', array_map(function (\DOMNode $child) {
      return $child->ownerDocument->saveXML($child);
    }, iterator_to_array($node->childNodes)));
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
