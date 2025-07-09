<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\ContentReviewInterface;
use Drupal\ghi_content\Entity\Document;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\Plugin\Block\Paragraph;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_blocks\Traits\PrivateMethodTrait;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;
use Prophecy\Argument;

/**
 * Tests the import manager.
 *
 * @group ghi_content
 */
class ImportManagerTest extends KernelTestBase {

  use TaxonomyTestTrait;
  use FieldTestTrait;
  use EntityReferenceFieldCreationTrait;
  use PrivateMethodTrait;
  use ContentTestTrait;

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
    'file',
    'filter',
    'hpc_api',
    'ghi_blocks',
    'ghi_content',
    'ghi_form_elements',
    'ghi_sections',
    'ghi_subpages',
  ];

  const BUNDLE = 'page';
  const ARTICLE_BUNDLE = 'article';
  const DOCUMENT_BUNDLE = 'document';
  const TAGS_VID = 'tags';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installConfig(['system', 'field', 'file']);

    NodeType::create(['type' => self::BUNDLE])->save();
    NodeType::create(['type' => self::ARTICLE_BUNDLE])->save();
    NodeType::create(['type' => self::DOCUMENT_BUNDLE])->save();
    $this->createVocabulary([
      'vid' => self::TAGS_VID,
    ]);
  }

  /**
   * Tests that the service is available.
   */
  public function testServiceAvailable() {
    $import_manager = \Drupal::service('ghi_content.import');
    $this->assertTrue($import_manager instanceof ImportManager, "The ImportManager service is available");
  }

  /**
   * Tests that text fields can be imported to articles.
   */
  public function testImportTextfieldArticle() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Create the short title field.
    $this->createField('node', self::ARTICLE_BUNDLE, 'string', 'field_short_title', 'Short title');

    $messenger = $this->prophesize(MessengerInterface::class);
    $messenger->addMessage(Argument::exact('Imported short title'))->shouldBeCalled();
    $messenger->addMessage(Argument::exact('Removed short title'))->shouldBeCalled();

    // Create a node.
    $article = Article::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'A node',
      'uid' => 0,
    ]);

    // Mock the article to be imported.
    $remote_article = $this->mockRemoteArticle([
      'title' => 'Burundi',
      'title_short' => 'Burundi short',
    ]);
    $import_manager->importTextfield($article, $remote_article, 'Short title', 'getShortTitle', 'field_short_title', 'plain_text', $messenger->reveal());
    $this->assertEquals('Burundi short', $article->getShortTitle());

    // Mock the article to be imported.
    $remote_article = $this->mockRemoteArticle([
      'title' => 'Burundi',
      'title_short' => NULL,
    ]);
    $import_manager->importTextfield($article, $remote_article, 'Short title', 'getShortTitle', 'field_short_title', 'plain_text', $messenger->reveal());
    $this->assertEquals(NULL, $article->getShortTitle());
  }

  /**
   * Tests that text fields can be imported to documents.
   */
  public function testImportTextfieldDocument() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Create the short title field.
    $this->createField('node', self::DOCUMENT_BUNDLE, 'string', 'field_short_title', 'Short title');

    $messenger = $this->prophesize(MessengerInterface::class);
    $messenger->addMessage(Argument::exact('Imported short title'))->shouldBeCalled();
    $messenger->addMessage(Argument::exact('Removed short title'))->shouldBeCalled();

    // Create a node.
    $document = Document::create([
      'type' => self::DOCUMENT_BUNDLE,
      'title' => 'A node',
      'uid' => 0,
    ]);

    // Mock the document to be imported.
    $remote_document = $this->mockRemoteDocument([
      'title' => 'Burundi',
      'title_short' => 'Burundi short',
    ]);
    $import_manager->importTextfield($document, $remote_document, 'Short title', 'getShortTitle', 'field_short_title', 'plain_text', $messenger->reveal());
    $this->assertEquals('Burundi short', $document->getShortTitle());

    // Mock the document to be imported.
    $remote_document = $this->mockRemoteDocument([
      'title' => 'Burundi',
      'title_short' => NULL,
    ]);
    $import_manager->importTextfield($document, $remote_document, 'Short title', 'getShortTitle', 'field_short_title', 'plain_text', $messenger->reveal());
    $this->assertEquals(NULL, $document->getShortTitle());
  }

  /**
   * Tests the import of article paragraphs.
   */
  public function testImportParagraphs() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    // Create the needs_review field.
    $this->createField('node', self::ARTICLE_BUNDLE, 'boolean', ContentReviewInterface::NEEDS_REVIEW_FIELD, 'Needs review');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('node', self::ARTICLE_BUNDLE);
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // Create an article.
    $article_node = Article::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'An article',
      'uid' => 0,
    ]);
    $this->assertInstanceOf(ContentReviewInterface::class, $article_node);

    // Mock the article to be imported.
    $remote_article = $this->mockRemoteArticleWithParagraphs(2);

    $sections = $article_node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $result = $import_manager->importArticleParagraphs($article_node, $remote_article, [], NULL, TRUE);
    $this->assertTrue($result);

    $sections = $article_node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $this->assertTrue(is_array($sections) && array_key_exists(0, $sections) && array_key_exists('section', $sections[0]), 'Section is set');

    // We expect 2 section components to be created.
    $section = $sections[0]['section'];
    $this->assertTrue($section instanceof Section, 'Section has the right type');
    $this->assertCount(2, $section->getComponents(), '2 components have been created' . print_r($section->getComponents(), TRUE));

    $paragraphs = array_values($remote_article->getParagraphs());

    // Make sure we have exactly the 2 paragraphs that we wanted.
    foreach (array_values($section->getComponents()) as $key => $component) {
      $plugin = $component->toArray();
      $this->assertEquals($paragraphs[$key]->getUuid(), $plugin['configuration']['sync']['source_uuid']);
    }

    // Make sure the needs review flag is not set.
    $this->assertFalse($article_node->needsReview());

    // Now add a paragraph.
    $remote_article = $this->mockRemoteArticleWithParagraphs(3);
    $result = $import_manager->importArticleParagraphs($article_node, $remote_article, [], NULL, TRUE);
    $this->assertTrue($result);

    // We expect 3 section components to be created.
    $section = $sections[0]['section'];
    $this->assertCount(3, $section->getComponents(), '3 components have been created' . print_r($section->getComponents(), TRUE));

    // Make sure the needs review flag is set.
    $this->assertTrue($article_node->needsReview());
  }

  /**
   * Tests that multiple new paragraphs are positioned correctly.
   */
  public function dataProviderPositionNewParagraphs() {
    // The 'existing_plugins' holds a list of to be created plugin types that
    // will be turned into section components during the test.
    // The 'expected_order' holds the keys of the section components in
    // sequential order as added to the section. To be created remote
    // paragraphs are recieiving IDs in sequential order.
    // The 'new_paragraphs' are the additional remote paragraphs to be imported
    // (and positioned) during the test. The number(s) represent the ID of each
    // remote paragraph.
    // The 'remote_order' lists the IDs of the remote paragraph in order.
    // The 'expected_order' lists the keys of all the section components
    // (existing plugins + newly added paragraphs) after the auto positioning.
    $test_cases = [
      [
        'existing_plugins' => [
          0 => 'block',
          1 => 'paragraph',
          2 => 'block',
        ],
        'new_paragraphs' => [2],
        'remote_order' => [1, 2],
        'expected_order' => [0, 1, 3, 2],
      ],
      [
        'existing_plugins' => [
          0 => 'paragraph',
          1 => 'paragraph',
          2 => 'paragraph',
          3 => 'block',
        ],
        'new_paragraphs' => [4],
        'remote_order' => [1, 4, 2, 3],
        'expected_order' => [0, 4, 1, 2, 3],
      ],
      [
        'existing_plugins' => [
          0 => 'paragraph',
          1 => 'paragraph',
          2 => 'paragraph',
          3 => 'block',
        ],
        'new_paragraphs' => [4],
        'remote_order' => [1, 3, 4, 2],
        'expected_order' => [0, 1, 2, 4, 3],
      ],
      [
        'existing_plugins' => [
          0 => 'paragraph',
          1 => 'paragraph',
          2 => 'paragraph',
        ],
        'new_paragraphs' => [4, 5],
        'remote_order' => [1, 4, 2, 5, 3],
        'expected_order' => [0, 3, 1, 4, 2],
      ],
      [
        'existing_plugins' => [
          0 => 'block',
          1 => 'paragraph',
          2 => 'block',
        ],
        'new_paragraphs' => [2],
        'remote_order' => [2, 1],
        'expected_order' => [0, 3, 1, 2],
      ],
      [
        'existing_plugins' => [
          0 => 'block',
          1 => 'paragraph',
          2 => 'paragraph',
          3 => 'paragraph',
          4 => 'block',
        ],
        'new_paragraphs' => [4, 5],
        'remote_order' => [4, 5, 1, 2, 3],
        'expected_order' => [0, 5, 6, 1, 2, 3, 4],
      ],
      [
        'existing_plugins' => [
          0 => 'block',
          1 => 'paragraph',
          2 => 'paragraph',
          3 => 'paragraph',
          4 => 'paragraph',
          5 => 'paragraph',
          6 => 'paragraph',
          7 => 'paragraph',
          8 => 'paragraph',
          9 => 'block',
        ],
        'new_paragraphs' => [9, 10, 11, 12],
        'remote_order' => [1, 2, 9, 10, 3, 4, 5, 12, 6, 7, 11, 8],
        'expected_order' => [0, 1, 2, 10, 11, 3, 4, 5, 13, 6, 7, 12, 8, 9],
      ],
      // Now a special test case where the order of the defined existing
      // section plugins does not represent the actual display order. The
      // 'existing_plugins' lists the plugins in the order defined and each
      // value has as the first item the plugin type and as a second item the
      // display weight.
      [
        'existing_plugins' => [
          0 => ['block', 2],
          1 => ['paragraph', 1],
          2 => ['block', 0],
        ],
        'new_paragraphs' => [2],
        'remote_order' => [1, 2],
        'expected_order' => [2, 1, 3, 0],
      ],
    ];
    return $test_cases;
  }

  /**
   * Tests that multiple new paragraphs are positioned correctly.
   *
   * @dataProvider dataProviderPositionNewParagraphs
   */
  public function testPositionNewParagraphs($existing_plugins, $new_paragraphs, $remote_order, $expected_order) {

    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Prepare the plugins and paragraphs according to $existing_plugins.
    $remote_paragraphs = [];
    $section_plugins = [];
    $paragraph_uuids = [];

    foreach ($existing_plugins as $weight => $plugin_type) {
      if (is_array($plugin_type)) {
        [$plugin_type, $weight] = $plugin_type;
      }
      $uuid = $weight . '-' . $plugin_type . '-' . $this->randomString();
      $plugin = match ($plugin_type) {
        'block' => $this->prophesize(BlockPluginInterface::class)->reveal(),
        'paragraph' => $this->mockParagraphPlugin($this->mockRemoteParagraph(count($paragraph_uuids) + 1)),
      };
      if ($plugin instanceof Paragraph) {
        $remote_paragraph = $plugin->getParagraph();
        $remote_paragraphs[$remote_paragraph->getId()] = $remote_paragraph;
        $paragraph_uuids[$remote_paragraph->getId()] = $uuid;
      }
      $section_plugins[$uuid] = [
        'weight' => $weight,
        'plugin' => $plugin,
      ];
    }

    // Then build the section components for these plugins.
    $section_components = [];
    $section = $this->mockSectionWithPlugins($section_plugins, $section_components);

    // Now mock the new remote paragraphs.
    foreach ($new_paragraphs as $paragraph_id) {
      $remote_paragraphs[$paragraph_id] = $this->mockRemoteParagraph($paragraph_id);
    }

    // And define their order on the remote.
    $paragraphs = [];
    foreach ($remote_order as $pid) {
      $paragraphs[$pid] = $remote_paragraphs[$pid];
    }

    // Add a component for each of the new paragraphs. By default, these go to
    // the end of the existing components.
    $new_components = [];
    foreach ($new_paragraphs as $paragraph_id) {
      $remote_paragraph = $remote_paragraphs[$paragraph_id];
      // Mock a section component for the new paragraph.
      $paragraph = $this->mockParagraphPlugin($remote_paragraph);
      $section = $this->addPluginComponentToSection($paragraph, NULL, $section_components);
      $component = end($section_components);
      $new_components[] = $component;
      $paragraph_uuids[$remote_paragraph->getId()] = $component->getUuid();
    }

    // Now call ImportManager::positionNewParagraphs to get the new order of
    // all components.
    $new_order = $this->callPrivateMethod($import_manager, 'positionNewParagraphs', [
      $section,
      $new_components,
      $paragraphs,
    ]);
    $this->assertCount(count($expected_order), $section_components);

    // And compare the new order with the expected result.
    $expected = [];
    foreach ($expected_order as $key) {
      $expected[] = array_values($section_components)[$key]->getUuid();
    }
    $this->assertEquals($expected, $new_order);
  }

  /**
   * Tests that tags can be imported.
   */
  public function testImportTags() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Setup the tags field on our node type.
    $this->createField('taxonomy_term', self::TAGS_VID, 'string', 'field_type', 'Type');
    $this->createEntityReferenceField('node', self::BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', [
      'target_bundles' => [
        self::TAGS_VID => self::TAGS_VID,
      ],
    ]);

    // Create a node.
    $node = Node::create([
      'type' => self::BUNDLE,
      'title' => 'A node',
      'uid' => 0,
    ]);

    // Mock the remote source.
    $remote_source = $this->createMock('Drupal\ghi_content\Plugin\RemoteSource\HpcContentModule');

    // Mock the article to be imported.
    $article = new RemoteArticle((object) [
      'id' => 42,
      'tags' => [
        'HRP',
      ],
      'content_space' => (object) [
        'id' => 24,
        'title' => 'Nigeria HRP 2021',
        'tags' => [
          'Nigeria',
          '2021',
        ],
      ],
    ], $remote_source);

    $this->assertEmpty($node->get('field_tags')->getValue());
    $result = $import_manager->importTags($node, $article, 'field_tags');
    $this->assertTrue($result);

    $imported_tags = $node->get('field_tags')->getValue();
    $this->assertCount(3, $imported_tags);

    $expected_term_names = ['HRP', 'Nigeria', '2021'];
    $tids = array_map(function ($tag) {
      return $tag['target_id'];
    }, $imported_tags);
    foreach ($tids as $tid) {
      /** @var \Drupal\taxonomy\Entity\Term $term */
      $term = Term::load($tid);
      $this->assertContains($term->label(), $expected_term_names);
    }
  }

}
