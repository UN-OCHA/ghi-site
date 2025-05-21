<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;

/**
 * Bundle class for section nodes.
 */
class Article extends ContentBase implements ContentReviewInterface {

  /**
   * Get the current context node.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The context node if set.
   */
  public function getContextNode() {
    if (!$this->contextNode) {
      $document = $this->getCurrentDocumentNode();
      if ($document && $this->isValidContextNode($document)) {
        $this->setContextNode($document);
      }
    }
    return parent::getContextNode();
  }

  /**
   * {@inheritdoc}
   */
  public function isValidContextNode($node) {
    if ($node instanceof Document) {
      return $node->hasArticle($this);
    }
    return parent::isValidContextNode($node);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if (!$this->id()) {
      return parent::getCacheTags();
    }
    $cache_tags = &drupal_static(__FUNCTION__ . '_' . $this->id(), NULL);
    if ($cache_tags === NULL) {
      $cache_tags = parent::getCacheTags();
      $documents = $this->getDocuments();
      foreach ($documents as $document) {
        $cache_tags = Cache::mergeTags($cache_tags, $document->getCacheTags());
      }
    }
    return $cache_tags;
  }

  /**
   * Get the document chapter to which this article belongs.
   *
   * This assumes that every article can only appear once per document.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The document node.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface
   *   The chapter object.
   */
  public function getDocumentChapter(Document $document) {
    foreach ($document->getChapters() as $chapter) {
      $articles = $document->getChapterArticles($chapter);
      foreach ($articles as $article) {
        if ($article->id() == $this->id()) {
          return $chapter;
        }
      }
    }
    return NULL;
  }

  /**
   * Get the documents that this article belongs to.
   *
   * @return \Drupal\ghi_content\Entity\Document[]
   *   An array of document nodes that this article belongs to.
   */
  public function getDocuments() {
    if (!$this->id()) {
      return [];
    }
    $documents = &drupal_static(__FUNCTION__ . $this->id(), NULL);
    if ($documents === NULL) {
      $article_manager = $this->getContentManager();
      $remote_article = $article_manager->loadRemoteContentForNode($this);
      if (!$remote_article instanceof RemoteArticleInterface) {
        return [];
      }
      $document_ids = $remote_article->getDocumentIds();
      $remote_source = $remote_article->getSource()->getPluginId();

      /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceManager $remote_source_manager */
      $remote_source_manager = \Drupal::service('plugin.manager.remote_source');

      if (!$remote_source || !$remote_source_manager->hasDefinition($remote_source)) {
        return [];
      }

      /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
      $remote_source_instance = $remote_source_manager->createInstance($remote_source);
      $document_manager = self::getDocumentManager();
      $documents = array_filter(array_map(function ($document_id) use ($document_manager, $remote_source_instance) {
        $remote_document = $remote_source_instance->getDocument($document_id);
        return $remote_document ? $document_manager->loadNodeForRemoteContent($remote_document) : NULL;
      }, $document_ids));
    }
    return $documents;
  }

  /**
   * Check if the given articles is a sub-article of the current one.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The article to check.
   *
   * @return bool
   *   TRUE if the given article is a sub-article of the current article, FALSE
   *   otherwise.
   */
  public function hasSubarticle(Article $article) {
    $remote_current = $this->getContentManager()->loadRemoteContentForNode($this);
    if (!$remote_current instanceof RemoteArticleInterface) {
      return FALSE;
    }
    $remote_article = $article->getContentManager()->loadRemoteContentForNode($article);
    if (!$remote_article instanceof RemoteArticleInterface) {
      return FALSE;
    }
    return $remote_current->hasSubarticle($remote_article);
  }

  /**
   * {@inheritdoc}
   */
  public function needsReview(?bool $state = NULL) {
    if (!$this->hasField(ContentReviewInterface::NEEDS_REVIEW_FIELD)) {
      return NULL;
    }
    if ($state === NULL) {
      return (bool) $this->get(ContentReviewInterface::NEEDS_REVIEW_FIELD)->value;
    }
    $this->get(ContentReviewInterface::NEEDS_REVIEW_FIELD)->setValue($state);
  }

  /**
   * Get the document manager.
   *
   * @return \Drupal\ghi_content\ContentManager\DocumentManager
   *   The document manager service.
   */
  public static function getDocumentManager() {
    return \Drupal::service('ghi_content.manager.document');
  }

}
