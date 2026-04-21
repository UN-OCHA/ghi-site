<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\node\NodeInterface;

/**
 * Base class for subpage nodes.
 */
class Document extends ContentBase {

  /**
   * {@inheritdoc}
   */
  public function getDataLayerDocumentProperties() {
    $data_layer = parent::getDataLayerDocumentProperties();
    $data_layer[$this->bundle() . 'Title'] = $this->label();
    return $data_layer;
  }

  /**
   * Check if the given article is part of this document.
   */
  public function hasArticle(Article $article) {
    foreach ($this->getChapters() as $chapter) {
      $articles = $this->getChapterArticles($chapter);
      $article_ids = array_map(function (NodeInterface $node) {
        return $node->id();
      }, $articles);
      if (in_array($article->id(), $article_ids)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Get the remote document.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface|null
   *   The remote document object if found.
   */
  public function getRemoteDocument() {
    $document_manager = $this->getDocumentManager();
    return $document_manager->loadRemoteContentForNode($this);
  }

  /**
   * Get the document chapters.
   *
   * @param bool $include_hidden
   *   Whether to fetch hidden chapters or not.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface[]
   *   The document chapters.
   */
  public function getChapters($include_hidden = TRUE) {
    $remote_document = $this->getRemoteDocument();
    if (!$remote_document instanceof RemoteDocumentInterface) {
      return [];
    }
    return $remote_document->getChapters($include_hidden);
  }

  /**
   * Get the articles for the given chapter.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteChapterInterface $chapter
   *   The chapter for which to load the articles.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   The articles for the given chapter.
   */
  public function getChapterArticles(RemoteChapterInterface $chapter) {
    $article_ids = $chapter->getArticleIds();
    $articles = $this->getArticleManager()->loadNodesForRemoteIds($chapter->getSource()->getPluginId(), $article_ids);
    $articles = array_filter(array_map(function ($article) {
      if (!$article || !$article->isPublished()) {
        return NULL;
      }
      // Cloning is important here, to prevent wrong links when the same
      // article is part of multiple documents.
      $clone = clone $article;
      if ($clone instanceof ContentBase) {
        $clone->setContextNode($this);
      }
      return $clone;
    }, $articles));
    return $articles;
  }

  /**
   * Get the chapter number.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteChapterInterface $chapter
   *   The chapter for which to get the number.
   *
   * @return int|false
   *   The chapter number, starting at 1, or FALSE.
   */
  public function getChapterNumber(RemoteChapterInterface $chapter) {
    $remote_document = $this->getRemoteDocument();
    return $remote_document->getChapterNumber($chapter->getId());
  }

  /**
   * Get the document summary.
   *
   * @return string
   *   The content of the summary field.
   */
  public function getSummary() {
    return $this->get('field_summary')->value;
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
      $article_ids = [];
      $remote_source = NULL;
      foreach ($this->getChapters() as $chapter) {
        $article_ids = array_merge($article_ids, $chapter->getArticleIds());
        $remote_source = $remote_source ?? $chapter->getSource()->getPluginId();
      }
      $article_ids = array_unique(array_filter($article_ids));
      if ($remote_source && $article_ids) {
        $articles = $this->getArticleManager()->loadNodesForRemoteIds($remote_source, $article_ids);
        foreach ($articles as $article) {
          if (!$article instanceof Article) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTagsToInvalidate());
        }
      }
    }
    return $cache_tags;
  }

  /**
   * Get the document manager service.
   *
   * @return \Drupal\ghi_content\ContentManager\DocumentManager
   *   The document manager service.
   */
  private static function getDocumentManager() {
    return \Drupal::service('ghi_content.manager.document');
  }

  /**
   * Get the article manager service.
   *
   * @return \Drupal\ghi_content\ContentManager\ArticleManager
   *   The article manager service.
   */
  private static function getArticleManager() {
    return \Drupal::service('ghi_content.manager.article');
  }

}
