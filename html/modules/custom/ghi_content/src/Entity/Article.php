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
  public function getDataLayerDocumentProperties() {
    $data_layer = parent::getDataLayerDocumentProperties();
    $document = $this->getCurrentDocumentNode();
    if ($document) {
      $data_layer += $document->getDataLayerDocumentProperties();
    }
    return $data_layer;
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
    $context_node = $this->getContextNode();
    $context_key = $context_node instanceof Document ? 'document_' . $context_node->id() : 'default';
    $cache_tags = &drupal_static(__FUNCTION__ . '_' . $this->id() . '_' . $context_key, NULL);
    if ($cache_tags === NULL) {
      $cache_tags = parent::getCacheTags();
      if ($context_node instanceof Document) {
        // The context document is already part of the parent render cache
        // metadata. Avoid remote lookups to rediscover article documents.
        return $cache_tags;
      }
      $documents = $this->getDocuments();
      foreach ($documents as $document) {
        $cache_tags = Cache::mergeTags($cache_tags, $document->getCacheTagsToInvalidate());
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
    $documents = &drupal_static(__CLASS__ . '::' . __FUNCTION__, []);

    $article_manager = $this->getContentManager();
    $remote_article = $article_manager->loadRemoteContentForNode($this);
    if (!$remote_article instanceof RemoteArticleInterface) {
      return [];
    }
    $document_ids = $remote_article->getDocumentIds();
    $load_document_ids = array_diff($document_ids, array_map(fn ($document) => $document->getSourceId(), $documents));
    if (!empty($load_document_ids)) {
      $remote_source = $remote_article->getSource()->getPluginId();
      $document_manager = self::getDocumentManager();
      $documents += $document_manager->loadNodesForRemoteIds($remote_source, $load_document_ids);
    }
    return array_filter($documents, fn ($document) => in_array($document->getSourceId(), $document_ids));
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
