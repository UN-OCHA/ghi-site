<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ghi_content\Import\ImportManager;
use Drupal\ghi_content\RemoteContent\HpcContentModule\RemoteArticle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests the import manager.
 *
 * @group ghi_content
 */
class ImportManagerTest extends KernelTestBase {

  use TaxonomyTestTrait;

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
    'entity_reference',
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

    $this->nodeType = NodeType::create(['type' => self::BUNDLE])->save();
    $this->vocabulary = $this->createVocabulary();
  }

  /**
   * Tests that the service is available.
   */
  public function testServiceAvailable() {
    $import_manager = \Drupal::service('ghi_content.import');

    // Test if hook_aggregator_fetcher_info_alter is being called.
    $this->assertTrue($import_manager instanceof ImportManager, "The ImportManager service is available");
  }

  /**
   * Tests that tags can be imported.
   */
  public function testImportParagraphs() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('node', self::BUNDLE);
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

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
      'title' => 'Nigeria',
      'content' => [
        (object) [
          'id' => 163,
          'uuid' => 'b02368e8-e310-4415-af81-feeacb8314c7',
          'type' => 'bottom_figure_row',
          'typeLabel' => 'Bottom figure row',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--bottom-figure-row paragraph--view-mode--top-figures gho-needs-and-requirements-paragraph content-width\">\n          <div class=\"gho-needs-and-requirements gho-figures gho-figures--large\">\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">People in need</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">8.3 million</div>\n  </div>\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">People targeted</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">5.4 million</div>\n  </div>\n  <div class=\"gho-needs-and-requirements-figure gho-figure\">\n    <div class=\"gho-needs-and-requirements-figure__label gho-figure__label\">Requirements (US$)</div>\n    <div class=\"gho-needs-and-requirements-figure__value gho-figure__value\">1.1 billion</div>\n  </div>\n</div>\n\n      </div>\n",
        ],
        (object) [
          'id' => 548,
          'uuid' => '2e959116-5a44-4271-9070-e44de5d0f32f',
          'type' => 'text',
          'typeLabel' => 'Text',
          'rendered' => "\n  <div class=\"paragraph paragraph--type--text paragraph--view-mode--default gho-text content-width\">\n          <gho-footnotes-list id=\"gho-footnotes-list-paragraph-548\"> test</gho-footnotes-list><div class=\"gho-text__text\"><gho-footnotes-text data-id=\"paragraph-548\"><p class=\"highlight\">Analysis of the context, crisis and needs</p>\n\n<p>Twelve years into the humanitarian crisis in north-east Nigeria’s Adamawa, Borno and Yobe States, the needs are as severe and large-scale as ever. The crisis continues unabated, and affected people’s living conditions are not improving; they still live with great unpredictability, privation far beyond chronic poverty, and daily threats to their health and safety. Crude mortality rates among people arriving from some inaccessible areas are at war-time levels. Food security[1] has improved somewhat, and cautious optimism about the course of the conflict was generated by the ‘surrender’ or escape in mid-2021 of some thousands of ‘fighters’ from non-State armed groups (NSAGs), though the majority are women and children. However, as attacks by NSAGs continue at scale, peace or true stabilization across most of the conflict-affected zones is not yet in sight. </p>\n\n<p>Protection needs are formidable, especially for women and girls, who still lack adequate protection and access to justice and services, and are at risk of violence, abduction, rape, gender-based violence, forced and child marriage, and other violations of their rights. Children are also at risk as unaccompanied and separated minors, and when formerly associated with armed groups, forced recruitment is a further risk. </p></gho-footnotes-text></div>\n\n      </div>\n",
        ],
      ],
    ], $remote_source);

    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $result = $import_manager->importArticleParagraphs($node, $article, [], NULL, TRUE);
    $this->assertTrue($result);

    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $this->assertTrue(is_array($sections) && array_key_exists(0, $sections) && array_key_exists('section', $sections[0]), 'Section is set');

    // We expect 2 section components to be created.
    $section = $sections[0]['section'];
    $this->assertTrue($section instanceof Section, 'Section has the right type');
    $this->assertCount(2, $section->getComponents(), '2 components have been created' . print_r($section->getComponents(), TRUE));

    $paragraphs = array_values($article->getParagraphs());

    // Make sure we have exactly the 2 paragraphs that we wanted.
    foreach (array_values($section->getComponents()) as $key => $component) {
      $plugin = $component->toArray();
      $this->assertEquals($paragraphs[$key]->getUuid(), $plugin['configuration']['sync']['source_uuid']);
    }
  }

  /**
   * Tests that tags can be imported.
   */
  public function testImportTags() {
    /** @var \Drupal\ghi_content\Import\ImportManager $import_manager */
    $import_manager = \Drupal::service('ghi_content.import');

    // Setup the tags field on our node type.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();
    $field = FieldConfig::create([
      'field_name' => 'field_tags',
      'field_storage' => $field_storage,
      'bundle' => self::BUNDLE,
    ]);
    $field->save();

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
