<?php

namespace Drupal\Tests\ghi_content\Kernel\Entity;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;

/**
 * Tests the article entity.
 *
 * @group ghi_content
 */
class ArticleTest extends KernelTestBase {

  use TaxonomyTestTrait;
  use UserCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use ContentTestTrait;
  use FieldTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'taxonomy',
    'field',
    'layout_builder',
    'layout_discovery',
    'migrate',
    'text',
    'filter',
    'file',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'ghi_sections',
    'ghi_content',
    'ghi_content_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'pathauto']);

    $hid_user_data = $this->createMock('\Drupal\hpc_common\Hid\HidUserData');

    $container = \Drupal::getContainer();
    $container->set('hpc_common.hid_user_data', $hid_user_data);
    \Drupal::setContainer($container);

    $this->createArticleContentType();
    $this->createDocumentContentType();

    $vocabulary = $this->createVocabulary();

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        $vocabulary->id() => $vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', DocumentManager::DOCUMENT_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests Article::isValidContextNode().
   */
  public function testIsValidContextNode() {
    // Case #1: An article can't be a valid context node for another article.
    $article_1 = $this->createArticle();
    $article_2 = $this->createArticle();
    $this->assertFalse($article_1->isValidContextNode($article_2));

    // Case #2: A document containing an article is a valid context node for
    // that article.
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 9,
      ],
    ]);
    $this->assertTrue($article->isValidContextNode($document));

    // Case #3: A document not containing an article is not a valid context
    // node for that article.
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 1,
      ],
    ]);
    $this->assertFalse($article->isValidContextNode($document));
  }

  /**
   * Tests Article::getCacheTags().
   */
  public function testGetCacheTags() {
    // Case #1: An article that doesn't belong to a document.
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 3,
      ],
    ]);
    $expected_cache_tags = [
      'node:' . $article->id(),
      'hpc_content_module_test:article:3',
    ];
    $this->assertEquals($expected_cache_tags, $article->getCacheTags());

    // Case #2: An article that does belong to a document.
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 9,
      ],
    ]);
    $expected_cache_tags = [
      // Cache tags from the article.
      'node:' . $article->id(),
      'hpc_content_module_test:article:9',
      // Cache tags from the document.
      'node:' . $document->id(),
      'hpc_content_module_test:document:597',
    ];
    $this->assertEquals($expected_cache_tags, $article->getCacheTags());
  }

  /**
   * Tests Article::getDocuments().
   */
  public function testGetDocuments() {
    // Case #1: An article that does belong to a document.
    $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 9,
      ],
    ]);
    $this->assertNotEmpty($article->getDocuments());

    // Case #2: An article that does not belong to a document.
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 1,
      ],
    ]);
    $this->assertEmpty($article->getDocuments());
  }

  /**
   * Tests Article::getDocumentChapter().
   */
  public function testGetDocumentChapter() {
    // Case #1: An article that is part of a document.
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $article_9 = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 9,
      ],
    ]);
    $chapter = $article_9->getDocumentChapter($document);
    $this->assertInstanceOf(RemoteChapterInterface::class, $chapter);

    // Case #2: An article that is not part of a document.
    $article_3 = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 3,
      ],
    ]);
    $this->assertNull($article_3->getDocumentChapter($document));
  }

  /**
   * Tests Article::hasSubarticle().
   */
  public function testHasSubarticle() {
    // Case #1: article 2 is a sub article of article 1 and we have fixtures
    // for both article ids.
    $article_1 = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 98,
      ],
    ]);
    $article_2 = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 99,
      ],
    ]);
    $this->assertTrue($article_1->hasSubarticle($article_2));

    // Case #2: article 3 has no fixture so it won't load the remote content
    // and it is not a sub article of article 1, neither is article 1 a sub
    // article of article 3, because article 3 doesn't exist on the remote.
    $article_3 = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 999,
      ],
    ]);
    $this->assertFalse($article_1->hasSubarticle($article_3));
    $this->assertFalse($article_3->hasSubarticle($article_1));
  }

  /**
   * Tests Article::needsReview().
   */
  public function testNeedsReview() {
    $article = $this->createArticle();
    $this->assertNull($article->needsReview());

    $this->createField('node', ArticleManager::ARTICLE_BUNDLE, 'boolean', ContentReviewInterface::NEEDS_REVIEW_FIELD, 'Needs review');
    $article = $this->createArticle();
    $this->assertFalse($article->needsReview());

    $article->needsReview(TRUE);
    $this->assertTrue($article->needsReview());
  }

}
