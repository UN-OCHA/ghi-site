<?php

namespace Drupal\ghi_content\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteContentInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\node\NodeInterface;

/**
 * Base class for subpage nodes.
 */
class Document extends ContentBase {

  /**
   * Check if the given article is part of this document.
   */
  public function hasArticle(Article $article) {
    foreach ($this->getChapters() as $chapter) {
      $articles = $this->getChapterArticles($chapter, FALSE);
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
   * Get the document chapters.
   *
   * @param bool $include_hidden
   *   Whether to fetch hidden chapters or not.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface[]
   *   The document chapters.
   */
  public function getChapters($include_hidden = TRUE) {
    $document_manager = $this->getDocumentManager();
    $remote_document = $document_manager->loadRemoteContentForNode($this);
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
    $articles = array_filter(array_map(function (RemoteContentInterface $remote_article) {
      $_node = $this->getArticleManager()->loadNodeForRemoteContent($remote_article);
      if (!$_node || !$_node->isPublished()) {
        return NULL;
      }
      // Cloning is important here, to prevent wrong links when the same
      // article is part of multiple documents.
      $node = clone $_node;
      if ($node instanceof ContentBase) {
        $node->setContextNode($this);
      }
      return $node;
    }, $chapter->getArticles()));
    return $articles;
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
      foreach ($this->getChapters() as $chapter) {
        foreach ($chapter->getArticles() as $remote_article) {
          $article = $this->getArticleManager()->loadNodeForRemoteContent($remote_article);
          if (!$article instanceof Article) {
            continue;
          }
          $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTags());
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
