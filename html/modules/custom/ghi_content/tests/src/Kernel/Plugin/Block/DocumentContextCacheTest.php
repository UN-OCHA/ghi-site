<?php

namespace Drupal\Tests\ghi_content\Kernel\Plugin\Block;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document as DocumentNode;
use Drupal\ghi_content\Plugin\Block\Document;
use Drupal\ghi_content\Plugin\Block\DocumentChapter;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteChapter;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\ghi_content\RemoteContent\RemoteDocumentInterface;
use Drupal\ghi_content\RemoteSource\RemoteSourceInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests document block cache metadata for rejected article contexts.
 *
 * @group ghi_content
 */
class DocumentContextCacheTest extends KernelTestBase {

  use ContentTestTrait;
  use EntityReferenceFieldCreationTrait;
  use FieldTestTrait;
  use TaxonomyTestTrait;
  use UserCreationTrait;

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

    $this->createArticleContentType();
    $this->createDocumentContentType();

    $vocabulary = $this->createVocabulary();
    $handler_settings = [
      'target_bundles' => [
        $vocabulary->id() => $vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', DocumentManager::DOCUMENT_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->setUpCurrentUser(['uid' => 1], ['access content']);
  }

  /**
   * Tests that document blocks do not cache rejected article contexts.
   */
  public function testDocumentBlockRejectsContextWithoutCaching(): void {
    $this->createBlockArticle();
    $document_node = $this->createRejectedContextDocumentNode();
    $chapter = $this->createChapter();
    $remote_document = $this->createRemoteDocument($chapter);

    $plugin = $this->getMockBuilder(Document::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getDocument'])
      ->getMock();
    $plugin->method('getDocument')->willReturn($remote_document);
    $this->setProtectedProperty($plugin, 'configuration', [
      'hpc' => [],
    ]);
    $this->setProtectedProperty($plugin, 'documentManager', $this->createDocumentManager($document_node));
    $this->setProtectedProperty($plugin, 'documentArticleContext', \Drupal::service('ghi_content.document_article_context'));

    $build = $plugin->buildContent();

    $this->assertSame(0, $build['#cache']['max-age']);
    $this->assertContains('node:' . $document_node->id(), $build['#cache']['tags']);
    $rendered_article = $build[0]['#tabs'][0]['items']['#articles'][0];
    $this->assertSame(0, $rendered_article->getCacheMaxAge());
  }

  /**
   * Tests that document chapter blocks do not cache rejected article contexts.
   */
  public function testDocumentChapterBlockRejectsContextWithoutCaching(): void {
    $this->createBlockArticle();
    $document_node = $this->createRejectedContextDocumentNode();
    $chapter = $this->createChapter();
    $remote_document = $this->createRemoteDocument($chapter);

    $plugin = $this->getMockBuilder(DocumentChapter::class)
      ->disableOriginalConstructor()
      ->onlyMethods([
        'getChapter',
        'getDocument',
      ])
      ->getMock();
    $plugin->method('getChapter')->willReturn($chapter);
    $plugin->method('getDocument')->willReturn($remote_document);
    $this->setProtectedProperty($plugin, 'configuration', [
      'hpc' => [],
    ]);
    $this->setProtectedProperty($plugin, 'documentManager', $this->createDocumentManager($document_node));
    $this->setProtectedProperty($plugin, 'documentArticleContext', \Drupal::service('ghi_content.document_article_context'));

    $build = $plugin->buildContent();

    $this->assertSame(0, $build['#cache']['max-age']);
    $this->assertContains('node:' . $document_node->id(), $build['#cache']['tags']);
    $rendered_article = $build[0]['#tabs'][0]['items']['article_collection']['#articles'][0];
    $this->assertSame(0, $rendered_article->getCacheMaxAge());
  }

  /**
   * Creates an article returned by the block article manager.
   *
   * @return \Drupal\ghi_content\Entity\Article
   *   The article.
   */
  private function createBlockArticle(): Article {
    return $this->createArticle([
      'status' => NodeInterface::PUBLISHED,
      ArticleManager::REMOTE_ARTICLE_FIELD => [
        'remote_source' => 'test_source',
        'article_id' => 123,
      ],
    ]);
  }

  /**
   * Creates a document node without a matching article relationship.
   *
   * @return \Drupal\ghi_content\Entity\Document
   *   The document node.
   */
  private function createRejectedContextDocumentNode(): DocumentNode {
    return $this->createDocument([
      'status' => NodeInterface::PUBLISHED,
    ]);
  }

  /**
   * Creates a remote document with the given chapter.
   *
   * @param \Drupal\ghi_content\RemoteContent\RemoteChapterInterface $chapter
   *   The chapter.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteDocumentInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The remote document.
   */
  private function createRemoteDocument(RemoteChapterInterface $chapter): RemoteDocumentInterface {
    $remote_document = $this->createMock(RemoteDocumentInterface::class);
    $remote_document->method('getChapters')->with(FALSE)->willReturn([$chapter]);
    return $remote_document;
  }

  /**
   * Creates a remote chapter that references one local article.
   *
   * @return \Drupal\ghi_content\RemoteContent\RemoteChapterInterface|\PHPUnit\Framework\MockObject\MockObject
   *   The remote chapter.
   */
  private function createChapter(): RemoteChapterInterface {
    $remote_source = $this->createMock(RemoteSourceInterface::class);
    $remote_source->method('getPluginId')->willReturn('test_source');

    return new RemoteChapter((object) [
      'id' => 456,
      'uuid' => '10000000-0000-1000-a000-000000000001',
      'title' => 'Chapter title',
      'title_short' => 'Chapter title',
      'summary' => '',
      'hidden' => FALSE,
      'articles' => [
        (object) [
          'id' => 123,
        ],
      ],
    ], $remote_source);
  }

  /**
   * Creates a document manager that returns the given document node.
   *
   * @param \Drupal\ghi_content\Entity\Document $document_node
   *   The document node to return.
   *
   * @return \Drupal\ghi_content\ContentManager\DocumentManager|\PHPUnit\Framework\MockObject\MockObject
   *   The document manager.
   */
  private function createDocumentManager(DocumentNode $document_node): DocumentManager {
    $document_manager = $this->getMockBuilder(DocumentManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['loadNodeForRemoteContent'])
      ->getMock();
    $document_manager->method('loadNodeForRemoteContent')->willReturn($document_node);
    return $document_manager;
  }

  /**
   * Sets a protected property on an object.
   *
   * @param object $object
   *   The object.
   * @param string $property
   *   The property name.
   * @param mixed $value
   *   The property value.
   */
  private function setProtectedProperty(object $object, string $property, $value): void {
    $reflection_property = new \ReflectionProperty($object, $property);
    $reflection_property->setAccessible(TRUE);
    $reflection_property->setValue($object, $value);
  }

}
