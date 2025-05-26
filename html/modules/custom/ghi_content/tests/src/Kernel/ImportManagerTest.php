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
use Drupal\ghi_content\RemoteContent\RemoteParagraphInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
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
    $this->assertCount(3, $section->getComponents(), '2 components have been created' . print_r($section->getComponents(), TRUE));

    // Make sure the needs review flag is not set.
    $this->assertTrue($article_node->needsReview());
  }

  /**
   * Tests that new paragraphs are positioned correctly.
   */
  public function testPositionNewParagraph() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Prepare the arguments for ImportManager::positionNewParagraph().
    $section = $this->prophesize(Section::class);
    $remote_paragraphs[] = [];
    $paragraph_uuids = [];
    $component_uuids = [];
    /** @var \Drupal\layout_builder\SectionComponent[] $section_components */
    $section_components = array_map(function ($weight) use (&$remote_paragraphs, &$paragraph_uuids, &$component_uuids) {
      $uuid = $this->randomString();
      $component = $this->prophesize(SectionComponent::class);
      $component->getWeight()->willReturn($weight);
      $component->getUuid()->willReturn($uuid);
      $component_uuids[$weight] = $uuid;

      // The first and the last components should be non-paragraph blocks for
      // the purpose of this test. The component with weight 10 represents the
      // newly added component.
      if ($weight != 0 && $weight != 9) {
        // Paragraph element.
        $paragraph_id = $weight;
        $remote_paragraph = $this->prophesize(RemoteParagraphInterface::class);
        $remote_paragraph->getId()->willReturn($paragraph_id);
        $remote_paragraphs[$paragraph_id] = $remote_paragraph->reveal();
        $paragraph_uuids[$paragraph_id] = $uuid;

        $paragraph = $this->prophesize(Paragraph::class);
        $paragraph->getParagraph()->willReturn($remote_paragraph->reveal());

        $component->getPlugin()->willReturn($paragraph->reveal());
      }
      else {
        // Non-paragraph element.
        $other_plugin = $this->prophesize(BlockPluginInterface::class);
        $component->getPlugin()->willReturn($other_plugin->reveal());
      }
      $component->setWeight(Argument::any())->shouldBeCalled();
      return $component->reveal();
    }, range(0, 10));

    // Finalize mocking of the section object.
    $section->getComponents()->willReturn($section_components);
    foreach ($section_components as $section_component) {
      $section->getComponent($section_component->getUuid())->willReturn($section_component);
    }

    // This is the new component and its associated remote paragraph object.
    $component = end($section_components);
    $remote_paragraph = $component->getPlugin()->getParagraph();

    // Test adding a new paragraph in the first position, which should place it
    // as the second component, preceeding the first paragraph and following
    // the actual first component which is not a paragraph.
    $paragraphs = [
      $remote_paragraph->getId() => $remote_paragraph,
      $remote_paragraphs[1]->getId() => $remote_paragraphs[1],
      $remote_paragraphs[2]->getId() => $remote_paragraphs[2],
      $remote_paragraphs[3]->getId() => $remote_paragraphs[3],
      $remote_paragraphs[4]->getId() => $remote_paragraphs[4],
      $remote_paragraphs[5]->getId() => $remote_paragraphs[5],
      $remote_paragraphs[6]->getId() => $remote_paragraphs[6],
      $remote_paragraphs[7]->getId() => $remote_paragraphs[7],
      $remote_paragraphs[8]->getId() => $remote_paragraphs[8],
    ];
    $new_order = $this->callPrivateMethod($import_manager, 'positionNewParagraph', [
      $section->reveal(),
      $component,
      $remote_paragraph,
      $paragraphs,
    ]);
    $expected = [
      0 => $component_uuids[0],
      1 => $paragraph_uuids[$remote_paragraph->getId()],
      2 => $paragraph_uuids[$remote_paragraphs[1]->getId()],
      3 => $paragraph_uuids[$remote_paragraphs[2]->getId()],
      4 => $paragraph_uuids[$remote_paragraphs[3]->getId()],
      5 => $paragraph_uuids[$remote_paragraphs[4]->getId()],
      6 => $paragraph_uuids[$remote_paragraphs[5]->getId()],
      7 => $paragraph_uuids[$remote_paragraphs[6]->getId()],
      8 => $paragraph_uuids[$remote_paragraphs[7]->getId()],
      9 => $paragraph_uuids[$remote_paragraphs[8]->getId()],
      10 => $component_uuids[9],
    ];
    $this->assertEquals($expected, $new_order);

    // Test adding a new paragraph in the last position, which should place it
    // as the second last component, following the last paragraph and
    // preceeding the actual last component which is not a paragraph.
    $paragraphs = [
      $remote_paragraphs[1]->getId() => $remote_paragraphs[1],
      $remote_paragraphs[2]->getId() => $remote_paragraphs[2],
      $remote_paragraphs[3]->getId() => $remote_paragraphs[3],
      $remote_paragraphs[4]->getId() => $remote_paragraphs[4],
      $remote_paragraphs[5]->getId() => $remote_paragraphs[5],
      $remote_paragraphs[6]->getId() => $remote_paragraphs[6],
      $remote_paragraphs[7]->getId() => $remote_paragraphs[7],
      $remote_paragraphs[8]->getId() => $remote_paragraphs[8],
      $remote_paragraph->getId() => $remote_paragraph,
    ];
    $new_order = $this->callPrivateMethod($import_manager, 'positionNewParagraph', [
      $section->reveal(),
      $component,
      $remote_paragraph,
      $paragraphs,
    ]);
    $expected = [
      0 => $component_uuids[0],
      1 => $paragraph_uuids[$remote_paragraphs[1]->getId()],
      2 => $paragraph_uuids[$remote_paragraphs[2]->getId()],
      3 => $paragraph_uuids[$remote_paragraphs[3]->getId()],
      4 => $paragraph_uuids[$remote_paragraphs[4]->getId()],
      5 => $paragraph_uuids[$remote_paragraphs[5]->getId()],
      6 => $paragraph_uuids[$remote_paragraphs[6]->getId()],
      7 => $paragraph_uuids[$remote_paragraphs[7]->getId()],
      8 => $paragraph_uuids[$remote_paragraphs[8]->getId()],
      9 => $paragraph_uuids[$remote_paragraph->getId()],
      10 => $component_uuids[9],
    ];
    $this->assertEquals($expected, $new_order);

    // Test adding a new paragraph in a middle position, which should place it
    // as the between the preceeding and the following paragraph.
    $paragraphs = [
      $remote_paragraphs[1]->getId() => $remote_paragraphs[1],
      $remote_paragraphs[2]->getId() => $remote_paragraphs[2],
      $remote_paragraphs[3]->getId() => $remote_paragraphs[3],
      $remote_paragraphs[4]->getId() => $remote_paragraphs[4],
      $remote_paragraph->getId() => $remote_paragraph,
      $remote_paragraphs[5]->getId() => $remote_paragraphs[5],
      $remote_paragraphs[6]->getId() => $remote_paragraphs[6],
      $remote_paragraphs[7]->getId() => $remote_paragraphs[7],
      $remote_paragraphs[8]->getId() => $remote_paragraphs[8],
    ];
    $new_order = $this->callPrivateMethod($import_manager, 'positionNewParagraph', [
      $section->reveal(),
      $component,
      $remote_paragraph,
      $paragraphs,
    ]);
    $expected = [
      0 => $component_uuids[0],
      1 => $paragraph_uuids[$remote_paragraphs[1]->getId()],
      2 => $paragraph_uuids[$remote_paragraphs[2]->getId()],
      3 => $paragraph_uuids[$remote_paragraphs[3]->getId()],
      4 => $paragraph_uuids[$remote_paragraphs[4]->getId()],
      5 => $paragraph_uuids[$remote_paragraph->getId()],
      6 => $paragraph_uuids[$remote_paragraphs[5]->getId()],
      7 => $paragraph_uuids[$remote_paragraphs[6]->getId()],
      8 => $paragraph_uuids[$remote_paragraphs[7]->getId()],
      9 => $paragraph_uuids[$remote_paragraphs[8]->getId()],
      10 => $component_uuids[9],
    ];
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
