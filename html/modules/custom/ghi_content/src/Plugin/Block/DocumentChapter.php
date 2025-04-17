<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;

/**
 * Provides a 'DocumentChapter' block.
 *
 * @Block(
 *  id = "document_chapter",
 *  admin_label = @Translation("Document chapter"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *  },
 *  config_forms = {
 *    "document_select" = {
 *      "title" = @Translation("Document selection"),
 *      "callback" = "documentSelectForm",
 *      "base_form" = TRUE
 *    },
 *    "chapter" = {
 *      "title" = @Translation("Chapter"),
 *      "callback" = "chapterForm"
 *    },
 *    "display" = {
 *      "title" = @Translation("Display"),
 *      "callback" = "displayForm"
 *    }
 *  }
 * )
 */
class DocumentChapter extends ContentBlockBase implements MultiStepFormBlockInterface, OverrideDefaultTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    // Work around an issue with updating "Show title". The logic for title
    // display in GHIBlockBase is confusing, but setting the configured label
    // to NULL here works for the moment.
    $configuration = parent::getConfiguration();
    $configuration['label'] = NULL;
    return $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $chapter = $this->getChapter();
    if (!$chapter) {
      return NULL;
    }
    $document_node = $this->documentManager->loadNodeForRemoteContent($this->getDocument());
    if (!$document_node) {
      return NULL;
    }
    $cache_tags = [];
    $articles = [];
    foreach ($this->getChapterArticles() as $_article) {
      $article = clone $_article;
      if ($document_node && $document_node instanceof ContentBase) {
        $article->setContextNode($document_node);
      }
      $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTags() ?? []);
      $articles[] = $article;
    }

    if (empty($articles)) {
      return NULL;
    }

    // Prepare the build.
    $build = [
      '#cache' => [
        'tags' => $cache_tags,
        'contexts' => ['url.path'],
      ],
    ];

    $tabs = [
      [
        'title' => [
          '#markup' => $chapter->getTitle(),
        ],
        'items' => [
          'summary' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#attributes' => [
              'class' => ['chapter-summary'],
            ],
            'summary' => [
              '#markup' => $chapter->getSummary(),
            ],
          ],
          'article_collection' => [
            '#theme' => 'article_collection_cards',
            '#title' => $this->t('Article collection'),
            '#articles' => $articles,
            '#options' => [
              'columns' => 3,
            ],
          ],
        ],
      ],
    ];

    if ($tabs) {
      $build[] = [
        '#theme' => 'tab_container',
        '#tabs' => $tabs,
      ];
    }
    if ($document_node->isProtected()) {
      $build['#attributes'] = [
        'class' => ['protected'],
      ];
    }
    return $build;
  }

  /**
   * Returns generic default configuration for block plugins.
   *
   * @return array
   *   An associative array with the default configuration.
   */
  protected function getConfigurationDefaults() {
    return [
      'document_select' => [
        'document' => [
          'remote_source' => NULL,
          'document_id' => NULL,
        ],
      ],
      'chapter' => [
        'chapter_id' => NULL,
      ],
      'display' => [
        'label' => NULL,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Overriding this should not be necessary, but there seems to be an issue
    // with GHIBlockBase's handling of OverrideDefaultTitleBlockInterface.
    return $this->getBlockConfig()['display']['label'] ?: parent::label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultTitle() {
    return $this->getChapter()?->getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultSubform($is_new = FALSE) {
    if (!$this->getDocument()) {
      return 'document_select';
    }
    return 'chapter';
  }

  /**
   * {@inheritdoc}
   */
  public function getTitleSubform() {
    return 'display';
  }

  /**
   * {@inheritdoc}
   */
  public function canShowSubform(array $form, FormStateInterface $form_state, $subform_key) {
    if ($subform_key == 'chapter') {
      return $this->getDocument() instanceof RemoteDocumentInterface;
    }
    return !$this->lockDocument();
  }

  /**
   * Select document form.
   */
  public function documentSelectForm(array $form, FormStateInterface $form_state) {
    $form['document'] = [
      '#type' => 'remote_document',
      '#default_value' => $this->getDocument(),
      '#required' => TRUE,
    ];

    $form['select_document'] = [
      '#type' => 'submit',
      '#value' => $this->t('Select this document'),
      '#element_submit' => [get_class($this) . '::ajaxMultiStepSubmit'],
      '#ajax' => [
        'callback' => [$this, 'navigateFormStep'],
        'wrapper' => $this->getContainerWrapper(),
        'effect' => 'fade',
        'method' => 'replace',
        'parents' => ['settings', 'container'],
      ],
      '#next_step' => 'chapter',
    ];

    return $form;
  }

  /**
   * Chapter config form.
   */
  public function chapterForm(array $form, FormStateInterface $form_state) {
    $document = $this->getDocument();
    $chapter = $this->getChapter();
    $form['document_summary'] = [
      '#type' => 'item',
      '#title' => $this->lockDocument() ? $this->t('Document (locked)') : $this->t('Selected document'),
      '#markup' => $document->getSource()->getPluginLabel() . ': ' . $document->getTitle(),
      '#weight' => -2,
    ];

    $form['label']['#weight'] = -1;
    $form['label']['#default_value'] = NULL;

    $options = array_map(function (RemoteChapterInterface $chapter) {
      $title = $chapter->getTitle();
      if ($chapter->isHidden()) {
        $title .= ' (hidden from navigation)';
      }
      return $title;
    }, $document->getChapters());

    $form['chapter_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Chapter'),
      '#description' => $this->t('Select a chapter from the document.'),
      '#options' => $options,
      '#limit' => 1,
      '#cols' => 3,
      '#default_value' => $chapter ? $chapter->getId() : NULL,
    ];
    return $form;
  }

  /**
   * Display form.
   */
  public function displayForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Get the configured document.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface
   *   The remote document.
   */
  public function getDocument() {
    $conf = $this->getBlockConfig();
    $remote_source_key = $conf['document_select']['document']['remote_source'] ?? NULL;
    if (!$remote_source_key) {
      return NULL;
    }
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $remote_source = $remote_source_manager->createInstance($remote_source_key);
    $document_id = $conf['document_select']['document']['document_id'] ?? NULL;
    if (!$remote_source || !$document_id) {
      return NULL;
    }
    return $remote_source->getDocument($document_id);
  }

  /**
   * Get the configured paragraph from the document.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface|null
   *   A chapter object as retrieved from the document.
   */
  private function getChapter() {
    $document = $this->getDocument();
    $conf = $this->getBlockConfig();
    if (!$document || empty($conf['chapter']['chapter_id'])) {
      return NULL;
    }
    $chapter_id = $conf['chapter']['chapter_id'];
    return $document->getChapter($chapter_id);
  }

  /**
   * Get the articles for the configured chapter.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   An array of article node objects.
   */
  private function getChapterArticles() {
    $chapter = $this->getChapter();
    $articles = $chapter ? array_filter(array_map(function (RemoteArticleInterface $article) {
      $article_node = $this->articleManager->loadNodeForRemoteContent($article);
      return $article_node?->access('view') ? $article_node : NULL;
    }, $chapter->getArticles())) : [];
    return $articles;
  }

  /**
   * Check if the document is locked for this chapter element.
   *
   * The document for a chapter is locked if there is a document set and if
   * additionally the lock_document flag is set in the configuration.
   *
   * @return bool
   *   TRUE if the document is locked, FALSE otherwise.
   */
  public function lockDocument() {
    return $this->getDocument() && !empty($this->configuration['lock_document']);
  }

}
