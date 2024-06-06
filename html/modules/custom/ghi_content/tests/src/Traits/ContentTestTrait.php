<?php

namespace Drupal\Tests\ghi_content\Traits;

use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentBase;
use Drupal\Tests\pathauto\Functional\PathautoTestHelperTrait;

/**
 * Provides methods to create content in tests.
 *
 * This trait is meant to be used only by test classes.
 */
trait ContentTestTrait {

  use PathautoTestHelperTrait;

  /**
   * Setup content types and content for these tests.
   */
  private function createArticleContentType() {
    $this->drupalCreateContentType([
      'type' => ArticleManager::ARTICLE_BUNDLE,
      'name' => 'Article page',
    ]);
    $this->createField('node', ArticleManager::ARTICLE_BUNDLE, 'ghi_remote_article', ArticleManager::REMOTE_ARTICLE_FIELD, 'Remote Article');

    $this->createEntityReferenceField('node', ArticleManager::ARTICLE_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term');

    $pattern = $this->createPattern('node', '/article/[node:title]');
    $this->addBundleCondition($pattern, 'node', ArticleManager::ARTICLE_BUNDLE);
    $pattern->save();
  }

  /**
   * Create an article.
   */
  public function createArticle(array $values = []) {
    $values += [
      'type' => ArticleManager::ARTICLE_BUNDLE,
      'title' => $this->randomString(),
    ];
    $article = Article::create($values);
    $this->assertSame(SAVED_NEW, $article->save());
    $this->assertInstanceOf(ContentBase::class, $article);
    return $article;
  }

}
