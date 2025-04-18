<?php

namespace Drupal\ghi_content\Plugin\Block;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ghi_blocks\Interfaces\AutomaticTitleBlockInterface;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteChapter;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;

/**
 * Provides a 'Document' block.
 *
 * @Block(
 *  id = "document",
 *  admin_label = @Translation("Document"),
 *  category = @Translation("Narrative Content"),
 *  context_definitions = {
 *    "node" = @ContextDefinition("entity:node", label = @Translation("Node"), required = FALSE),
 *  }
 * )
 */
class Document extends ContentBlockBase implements AutomaticTitleBlockInterface {

  /**
   * {@inheritdoc}
   */
  public function getAutomaticBlockTitle() {
    $document = $this->getDocument();
    return $document?->getTitle();
  }

  /**
   * {@inheritdoc}
   */
  public function buildContent() {
    $document = $this->getDocument();
    if (!$document) {
      return NULL;
    }
    $document_node = $this->documentManager->loadNodeForRemoteContent($document);
    if (!$document_node) {
      return NULL;
    }
    $cache_tags = [];

    $cache_tags = Cache::mergeTags($cache_tags, $document_node->getCacheTags());

    $conf = $this->getBlockConfig();
    $show_titles = $conf['show_titles'] ?? TRUE;

    $tabs = [];
    $chapters = $document->getChapters(FALSE);
    foreach ($chapters as $chapter) {
      $articles = [];
      foreach ($this->getChapterArticles($chapter) as $_article) {
        $article = clone $_article;
        if ($document_node && $document_node instanceof ContentBase) {
          $article->setContextNode($document_node);
        }
        $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTags() ?? []);
        $articles[] = $article;
      }
      $tabs[] = [
        'title' => [
          '#markup' => $show_titles ? $chapter->getShortTitle() : NULL,
        ],
        'items' => [
          '#theme' => 'article_collection_cards',
          '#title' => $chapter->getShortTitle(),
          '#articles' => $articles,
          '#options' => [
            'columns' => 3,
          ],
        ],
      ];
    }

    if (empty($articles)) {
      return NULL;
    }

    $build = [
      '#cache' => [
        'tags' => $cache_tags,
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
      'document' => [
        'remote_source' => NULL,
        'document_id' => NULL,
        'show_titles' => TRUE,
      ],
    ];
  }

  /**
   * Select document form.
   */
  public function getConfigForm(array $form, FormStateInterface $form_state) {
    $form['document'] = [
      '#type' => 'remote_document',
      '#default_value' => $this->getDocument(),
      '#required' => TRUE,
    ];

    $form['show_title'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the title'),
      '#default_value' => $this->getDefaultFormValueFromFormState($form_state, 'show_title'),
    ];

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
    $remote_source_key = $conf['document']['remote_source'] ?? NULL;
    if (!$remote_source_key) {
      return NULL;
    }
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
    $remote_source_manager = \Drupal::service('plugin.manager.remote_source');
    $remote_source = $remote_source_manager->createInstance($remote_source_key);
    $document_id = $conf['document']['document_id'] ?? NULL;
    if (!$remote_source || !$document_id) {
      return NULL;
    }
    return $remote_source->getDocument($document_id);
  }

  /**
   * Get the articles for the configured chapter.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   An array of article node objects.
   */
  private function getChapterArticles(RemoteChapter $chapter) {
    $articles = $chapter ? array_filter(array_map(function (RemoteArticleInterface $article) {
      $article_node = $this->articleManager->loadNodeForRemoteContent($article);
      return $article_node->access('view') ? $article_node : NULL;
    }, $chapter->getArticles())) : [];
    return $articles;
  }

}
