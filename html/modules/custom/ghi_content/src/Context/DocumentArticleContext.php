<?php

namespace Drupal\ghi_content\Context;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;

/**
 * Manages article relationships and rendering inside a document context.
 *
 * Documents define article membership through remote chapters. That membership
 * is used by path validation, document navigation, chapter-aware titles, cache
 * tags, and article cards. Keeping those rules here gives the rest of the site
 * a single place to ask document/article questions.
 *
 * Article entities can still be rendered by Drupal's normal node view builder.
 * Before handing them to that render pipeline, this service clones them and
 * applies a validated document context so their normal toUrl() calls produce
 * document-prefixed URLs. If validation fails, the related render output is
 * marked uncacheable so a temporary standalone URL cannot be stored.
 */
class DocumentArticleContext {

  /**
   * The article manager.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * Constructs a document article context service.
   *
   * @param \Drupal\ghi_content\ContentManager\ArticleManager $article_manager
   *   The article manager.
   */
  public function __construct(ArticleManager $article_manager) {
    $this->articleManager = $article_manager;
  }

  /**
   * Check if an article belongs to a document.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node.
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local article node.
   *
   * @return bool
   *   TRUE if the article belongs to the document.
   */
  public function documentHasArticle(Document $document, Article $article): bool {
    return $this->getArticleChapter($document, $article) !== NULL;
  }

  /**
   * Get the document chapter containing an article.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node.
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The local article node.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface|null
   *   The matching chapter, or NULL when the article is not part of the
   *   document.
   */
  public function getArticleChapter(Document $document, Article $article): ?RemoteChapterInterface {
    $article_source = $article->getSourceType();
    $article_id = $article->getSourceId();
    if (!$article_source || !$article_id) {
      return NULL;
    }

    foreach ($document->getChapters() as $chapter) {
      if ($chapter->getSource()->getPluginId() != $article_source) {
        continue;
      }
      $chapter_article_ids = array_map('intval', $chapter->getArticleIds());
      if (in_array((int) $article_id, $chapter_article_ids, TRUE)) {
        return $chapter;
      }
    }
    return NULL;
  }

  /**
   * Load published articles for a chapter and apply known-valid context.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node.
   * @param \Drupal\ghi_content\RemoteContent\RemoteChapterInterface $chapter
   *   The remote chapter.
   * @param bool $check_access
   *   Whether to apply node access checks.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   The local article nodes for the chapter.
   */
  public function getChapterArticles(Document $document, RemoteChapterInterface $chapter, bool $check_access = FALSE): array {
    $articles = $this->loadArticlesForChapter($chapter, $check_access);
    return array_values(array_map(function (Article $article) use ($document) {
      $contextual_article = clone $article;
      $contextual_article->setKnownValidContextNode($document);
      return $contextual_article;
    }, $articles));
  }

  /**
   * Load published local articles referenced by a remote chapter.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteChapterInterface $chapter
   *   The remote chapter.
   * @param bool $check_access
   *   Whether to apply node access checks.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   The local article nodes referenced by the chapter.
   */
  public function loadArticlesForChapter(RemoteChapterInterface $chapter, bool $check_access = FALSE): array {
    $article_ids = $chapter->getArticleIds();
    $source = $chapter->getSource()->getPluginId();
    $articles = $check_access ? $this->articleManager->loadAccessibleNodesForRemoteIds($source, $article_ids) : $this->articleManager->loadNodesForRemoteIds($source, $article_ids);
    $articles = array_filter($articles, function ($article) {
      return $article instanceof Article && $article->isPublished();
    });
    return array_values($articles);
  }

  /**
   * Get article cache tags for all articles referenced by a document.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node.
   *
   * @return string[]
   *   Cache tags for article invalidation.
   */
  public function getDocumentArticleCacheTags(Document $document): array {
    $cache_tags = [];
    $article_ids_by_source = [];
    foreach ($document->getChapters() as $chapter) {
      $source = $chapter->getSource()->getPluginId();
      $article_ids_by_source[$source] = array_merge(
        $article_ids_by_source[$source] ?? [],
        $chapter->getArticleIds()
      );
    }

    foreach ($article_ids_by_source as $source => $article_ids) {
      $article_ids = array_unique(array_filter($article_ids));
      if (!$article_ids) {
        continue;
      }
      $articles = $this->articleManager->loadNodesForRemoteIds($source, $article_ids);
      foreach ($articles as $article) {
        if ($article instanceof Article) {
          $cache_tags = Cache::mergeTags($cache_tags, $article->getCacheTagsToInvalidate());
        }
      }
    }
    return $cache_tags;
  }

  /**
   * Creates cacheability metadata for article cards in a document context.
   *
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node that provides the article context.
   *
   * @return \Drupal\Core\Cache\CacheableMetadata
   *   Cache metadata for the contextual article card list.
   */
  public function createCacheability(Document $document): CacheableMetadata {
    $cacheability = new CacheableMetadata();
    $cacheability->setCacheMaxAge(Cache::PERMANENT);
    $cacheability->setCacheTags($document->getCacheTagsToInvalidate());
    return $cacheability;
  }

  /**
   * Prepares multiple article clones for a document-context article card list.
   *
   * @param \Drupal\ghi_content\Entity\Article[] $articles
   *   The source article nodes.
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node that provides the article context.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cache metadata to update for the containing render array.
   *
   * @return \Drupal\ghi_content\Entity\Article[]
   *   Prepared article clones.
   */
  public function prepareArticles(array $articles, Document $document, CacheableMetadata $cacheability): array {
    return array_values(array_map(
      function (Article $article) use ($document, $cacheability) {
        return $this->prepareArticle($article, $document, $cacheability);
      },
      $articles
    ));
  }

  /**
   * Prepares one article clone for rendering in a document context.
   *
   * @param \Drupal\ghi_content\Entity\Article $article
   *   The source article node.
   * @param \Drupal\ghi_content\Entity\Document $document
   *   The local document node that provides the article context.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Cache metadata to update for the containing render array.
   *
   * @return \Drupal\ghi_content\Entity\Article
   *   The prepared article clone.
   */
  public function prepareArticle(Article $article, Document $document, CacheableMetadata $cacheability): Article {
    $contextual_article = clone $article;

    if (!$contextual_article->setContextNode($document)) {
      // Without a valid document context the card URL falls back to the
      // standalone article alias, so do not let that render output enter a
      // reusable cache.
      $cacheability->setCacheMaxAge(0);
      $contextual_article->mergeCacheMaxAge(0);
    }

    $cacheability->addCacheTags(
      $contextual_article->getCacheTagsToInvalidate()
    );
    return $contextual_article;
  }

}
