<?php

namespace Drupal\Tests\ghi_content\Kernel\Entity;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\ghi_content\RemoteContent\RemoteChapterInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\views\Entity\View;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the article entity.
 */
#[Group('ghi_content')]
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
    $this->assertSame([], $article->getReviewReasons(), 'Existing sites without the reasons field remain compatible.');
  }

  /**
   * Tests reasons accumulate, survive saves and clear with the review flag.
   */
  public function testReviewReasons(): void {
    $this->createField('node', ArticleManager::ARTICLE_BUNDLE, 'boolean', ContentReviewInterface::NEEDS_REVIEW_FIELD, 'Needs review');
    $config_directory = $this->root . '/../config/';
    FieldStorageConfig::create(Yaml::decode(file_get_contents($config_directory . 'field.storage.node.field_review_reasons.yml')))->save();
    FieldConfig::create(Yaml::decode(file_get_contents($config_directory . 'field.field.node.article.field_review_reasons.yml')))->save();
    $article = $this->createArticle();
    $this->assertSame([], $article->getReviewReasons());
    $display = $this->container->get('entity_display.repository')->getViewDisplay('node', ArticleManager::ARTICLE_BUNDLE);
    $this->assertNull($display->getComponent(ContentReviewInterface::REVIEW_REASONS_FIELD), 'Reasons are displayed in the editor review panel, not public article output.');

    $article->needsReview(TRUE);
    $article->needsReview(TRUE, ContentReviewInterface::REVIEW_REASON_PARAGRAPH_ORDER);
    $article->needsReview(TRUE, ContentReviewInterface::REVIEW_REASON_PARAGRAPH_ORDER);
    $article->needsReview(TRUE, ContentReviewInterface::REVIEW_REASON_IMAGE_DOWNLOAD);
    $article->save();
    $article = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($article->id());
    $reasons = $article->getReviewReasons();
    $this->assertTrue($article->needsReview());
    $this->assertCount(3, $reasons);
    $this->assertArrayHasKey(ContentReviewInterface::REVIEW_REASON_PARAGRAPH_ORDER, $reasons);
    $this->assertArrayHasKey(ContentReviewInterface::REVIEW_REASON_IMAGE_DOWNLOAD, $reasons);
    $this->assertSame('An editor flagged this article for review.', (string) $reasons[ContentReviewInterface::REVIEW_REASON_MANUAL]);

    $article->set(ContentReviewInterface::NEEDS_REVIEW_FIELD, FALSE);
    $article->save();
    $article = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($article->id());
    $this->assertSame([], $article->getReviewReasons());
    $this->assertTrue($article->get(ContentReviewInterface::REVIEW_REASONS_FIELD)->isEmpty(), 'Clearing the review checkbox also clears stored reasons.');

    $this->container->get('plugin.manager.action')->createInstance('needs_review_flag')->execute($article);
    $this->assertSame([ContentReviewInterface::REVIEW_REASON_MANUAL], array_keys($article->getReviewReasons()));
    $this->container->get('plugin.manager.action')->createInstance('needs_review_unflag')->execute($article);
    $article = $this->container->get('entity_type.manager')->getStorage('node')->loadUnchanged($article->id());
    $this->assertFalse($article->needsReview());
    $this->assertSame([], $article->getReviewReasons());
  }

  /**
   * Tests the review list filters and renders multiple readable reasons.
   */
  public function testReviewListReasons(): void {
    $this->enableModules(['views']);
    $this->installConfig(['views']);
    $this->container->get('theme_installer')->install(['stark']);
    $this->config('system.theme')->set('default', 'stark')->save();
    $this->createField('node', ArticleManager::ARTICLE_BUNDLE, 'boolean', ContentReviewInterface::NEEDS_REVIEW_FIELD, 'Needs review');
    $config_directory = $this->root . '/../config/';
    FieldStorageConfig::create(Yaml::decode(file_get_contents($config_directory . 'field.storage.node.field_review_reasons.yml')))->save();
    FieldConfig::create(Yaml::decode(file_get_contents($config_directory . 'field.field.node.article.field_review_reasons.yml')))->save();

    $ids = [];
    foreach ([
      [ContentReviewInterface::REVIEW_REASON_PARAGRAPH_ORDER, ContentReviewInterface::REVIEW_REASON_IMAGE_DOWNLOAD],
      [ContentReviewInterface::REVIEW_REASON_PARAGRAPHS_ADDED],
      [ContentReviewInterface::REVIEW_REASON_MANUAL],
      [],
    ] as $reasons) {
      $article = $this->createArticle();
      // The empty entry represents an older flag with no recorded reason.
      $article->set(ContentReviewInterface::NEEDS_REVIEW_FIELD, TRUE);
      foreach ($reasons as $reason) {
        $article->needsReview(TRUE, $reason);
      }
      $article->setSyncing(TRUE);
      $article->save();
      $ids[] = (int) $article->id();
    }
    $this->createArticle();

    $config = Yaml::decode(file_get_contents($config_directory . 'views.view.content.yml'));
    $review = $config['display']['page_review_queue']['display_options'];
    // Exercise the exported reason column and filters independently of the
    // other content-list fields, permissions and their configuration.
    $view_config = [
      'id' => 'review_reasons_test',
      'base_table' => 'node_field_data',
      'base_field' => 'nid',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_options' => [
            'fields' => ['field_review_reasons' => $review['fields']['field_review_reasons']],
            'filters' => array_intersect_key($review['filters'], [
              'field_needs_review_value' => TRUE,
              'field_review_reasons_value' => TRUE,
            ]),
            'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => TRUE]],
            'pager' => ['type' => 'none'],
            'cache' => ['type' => 'none'],
          ],
        ],
        'page_review_queue' => [
          'id' => 'page_review_queue',
          'display_plugin' => 'page',
          'display_options' => ['path' => 'test-review-reasons'],
        ],
      ],
    ];
    foreach (['All' => $ids, 1 => [$ids[0]], 2 => [$ids[1]], 3 => [$ids[0]], 4 => [$ids[2]]] as $selection => $expected) {
      $this->container->get('views.exposed_form_cache')->reset();
      $view = View::create($view_config)->getExecutable();
      $view->setDisplay('page_review_queue');
      $view->setExposedInput(['review_reason' => (string) $selection]);
      $view->execute();
      $actual = array_map(fn ($row) => (int) $row->_entity->id(), $view->result);
      $this->assertEqualsCanonicalizing($expected, $actual, 'Each matching article appears once, including older flags when no reason is selected.');
      if ($selection === 1) {
        $rendered = $this->container->get('renderer')->executeInRenderContext(new RenderContext(), fn () => (string) $view->field['field_review_reasons']->advancedRender($view->result[0]));
        $dom = Html::load($rendered);
        $xpath = new \DOMXPath($dom);
        $labels = $xpath->query('//span[@class="ghi-review-reason__label"]');
        $this->assertSame('Paragraph order changed in source', $labels->item(0)->textContent);
        $this->assertSame('Image download failed', $labels->item(1)->textContent, 'Filtering by one reason still displays all reasons for the article.');
        $buttons = $xpath->query('//button[@class="ghi-review-reason__help"]');
        $this->assertCount(2, $buttons);
        $this->assertSame('button', $buttons->item(0)->getAttribute('type'));
        $this->assertStringContainsString('focus', $buttons->item(0)->getAttribute('data-tippy-trigger'));
        $this->assertStringContainsString('could not be fully applied', $buttons->item(0)->getAttribute('data-tippy-content'));
        $this->assertStringNotContainsString('could not be fully applied', $dom->textContent, 'Full explanations are only shown in the tooltip.');
      }
      $view->destroy();
    }
  }

  /**
   * Tests Document::getDataLayerDocumentProperties().
   */
  public function testGetDataLayerDocumentProperties() {
    $article = $this->createArticle();
    $data_layer_properties = $article->getDataLayerDocumentProperties();
    $this->assertIsArray($data_layer_properties);
    $this->assertCount(0, $data_layer_properties);
  }

}
