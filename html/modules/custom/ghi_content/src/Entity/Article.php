<?php

namespace Drupal\ghi_content\Entity;

use Drupal\ghi_content\RemoteContent\RemoteArticleInterface;

/**
 * Bundle class for section nodes.
 */
class Article extends ContentBase {

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
    $content_manager = $this->getContentManager();
    $remote_article = $content_manager->loadRemoteContentForNode($this);
    if (!$remote_article instanceof RemoteArticleInterface) {
      return FALSE;
    }
    $document_ids = $remote_article->getDocumentIds();
    $remote_source = $remote_article->getSource()->getPluginId();
    /** @var \Drupal\ghi_content\RemoteSource\RemoteSourceInterface $remote_source_instance */
    $remote_source_instance = \Drupal::service('plugin.manager.remote_source')->remoteSourceManager->createInstance($remote_source);
    return array_filter(array_map(function ($document_id) use ($content_manager, $remote_source_instance) {
      $remote_document = $remote_source_instance->getDocument($document_id);
      return $content_manager->loadNodeForRemoteContent($remote_document);
    }, $document_ids));
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

}
