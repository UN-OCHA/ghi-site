<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;

/**
 * Tests the document entity.
 *
 * @group ghi_content
 */
class DocumentTest extends KernelTestBase {

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

  const SECTION_BUNDLE = 'section';
  const DOCUMENT_BUNDLE = 'document';

  /**
   * A vocabulary for tags.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The document manager to test.
   *
   * @var \Drupal\ghi_content\DocumentManager
   */
  protected $documentManager;

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

    $this->documentManager = \Drupal::service('ghi_content.manager.document');

    $this->createArticleContentType();
    $this->createDocumentContentType();

    $this->vocabulary = $this->createVocabulary();

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', DocumentManager::DOCUMENT_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests Document::getSummary().
   */
  public function testGetSummary() {
    $this->createField('node', DocumentManager::DOCUMENT_BUNDLE, 'text', 'field_summary', 'Summary');
    $document = $this->createDocument([
      'field_summary' => 'Summary',
    ]);
    $this->assertEquals('Summary', $document->getSummary());
  }

  /**
   * Tests Document::getCacheTags().
   */
  public function testGetCacheTags() {
    // First test case is a document without any articles (no fixtures for
    // document with id 2)
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 2,
      ],
    ]);
    $expected_cache_tags = [
      'node:' . $document->id(),
      'hpc_content_module_test:document:2',
    ];
    $this->assertEquals($expected_cache_tags, $document->getCacheTags());

    // Second test case is a document without a remote source.
    $document = $this->createDocument();
    $expected_cache_tags = [
      'node:' . $document->id(),
    ];
    $this->assertEquals($expected_cache_tags, $document->getCacheTags());

    // Third test case is a document with articles.
    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 9,
      ],
    ]);
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $expected_cache_tags = [
      // Cache tags from the document.
      'node:' . $document->id(),
      'hpc_content_module_test:document:597',
      // Cache tags from the article.
      'node:' . $article->id(),
      'hpc_content_module_test:article:9',
    ];
    $this->assertEquals($expected_cache_tags, $document->getCacheTags());
  }

  /**
   * Tests Document::getChapters().
   */
  public function testGetChapters() {
    $document = $this->createDocument([
      DocumentManager::REMOTE_DOCUMENT_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'document_id' => 597,
      ],
    ]);
    $chapters = $document->getChapters();
    $this->assertIsArray($chapters);
    $this->assertNotEmpty($chapters);
    $this->assertCount(5, $chapters);
  }

  /**
   * Tests Document::hasArticle().
   */
  public function testHasArticle() {
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
    $this->assertTrue($document->hasArticle($article));

    $article = $this->createArticle([
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'hpc_content_module_test',
        'article_id' => 1,
      ],
    ]);
    $this->assertFalse($document->hasArticle($article));
  }

}
