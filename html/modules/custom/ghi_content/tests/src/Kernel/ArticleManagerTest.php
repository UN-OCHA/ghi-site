<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_sections\Entity\Section;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the article manager.
 *
 * @group ghi_content
 */
class ArticleManagerTest extends KernelTestBase {

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
    'entity_reference',
    'layout_builder',
    'layout_discovery',
    'migrate',
    'text',
    'filter',
    'file',
    'token',
    'path_alias',
    'pathauto',
    'ghi_sections',
    'ghi_content',
  ];

  const SECTION_BUNDLE = 'section';
  const ARTICLE_BUNDLE = 'article';

  /**
   * A vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * The article manager to test.
   *
   * @var \Drupal\ghi_content\ContentManager\ArticleManager
   */
  protected $articleManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'file']);

    $this->articleManager = \Drupal::service('ghi_content.manager.article');

    NodeType::create(['type' => self::SECTION_BUNDLE])->save();
    NodeType::create(['type' => self::ARTICLE_BUNDLE])->save();

    $this->vocabulary = $this->createVocabulary();

    // Setup the tags field on our node types.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_tags',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'taxonomy_term',
      ],
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_tags',
      'field_storage' => $field_storage,
      'bundle' => self::SECTION_BUNDLE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $this->vocabulary->id() => $this->vocabulary->id(),
          ],
        ],
      ],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_tags',
      'field_storage' => $field_storage,
      'bundle' => self::ARTICLE_BUNDLE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            $this->vocabulary->id() => $this->vocabulary->id(),
          ],
        ],
      ],
    ])->save();

    // $this->setUpCurrentUser([], ['access content']);
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests that tags can be imported.
   */
  public function testGetTags() {
    $section_terms = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];
    $expected_section_term = [];
    foreach ($section_terms as $term) {
      $expected_section_term[$term->id()] = $term->label();
    }

    // Create a section.
    $section = Node::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
      'field_tags' => array_keys($expected_section_term),
    ]);
    $this->assertEquals($expected_section_term, $this->articleManager->getTags($section));
  }

  /**
   * Tests that tags can be imported.
   */
  public function testLoadNodesForSection() {

    $section_terms = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];
    $section_term_ids = array_map(function ($term) {
      return $term->id();
    }, $section_terms);

    // Create some article terms.
    $term_1 = $this->createTerm($this->vocabulary);
    $term_2 = $this->createTerm($this->vocabulary);
    $term_3 = $this->createTerm($this->vocabulary);
    $term_4 = $this->createTerm($this->vocabulary);

    // And create some more terms.
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);

    // Create a section.
    $section = Section::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
      'field_tags' => $section_term_ids,
    ]);
    $section->save();

    $this->assertEquals($section_term_ids, array_keys($this->articleManager->getTags($section)));
    $this->assertEquals([], array_keys($this->articleManager->loadNodesForSection($section)));

    // Create an article.
    $article_1_tags = array_merge($section_term_ids, [
      $term_1->id(),
      $term_2->id(),
    ]);
    $article_1 = Article::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'An article node',
      'status' => NodeInterface::PUBLISHED,
      'uid' => 0,
      'field_tags' => $article_1_tags,
    ]);
    $article_1->save();

    // Create another article.
    $article_2_tags = array_merge($section_term_ids, [
      $term_3->id(),
      $term_4->id(),
    ]);
    $article_2 = Article::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'An article node',
      'status' => NodeInterface::PUBLISHED,
      'uid' => 0,
      'field_tags' => $article_2_tags,
    ]);
    $article_2->save();

    // Check the number of articles found for a section.
    $section_articles = $this->articleManager->loadNodesForSection($section);
    $this->assertCount(2, array_keys($section_articles));

    // Check the available tags returned for the section articles.
    $expected_tags = array_unique(array_merge($article_1_tags, $article_2_tags));
    sort($expected_tags);
    $this->assertEquals($expected_tags, array_keys($this->articleManager->getAvailableTags($section_articles)));

  }

  /**
   * Tests that tags can be imported.
   */
  public function testLoadAvailableTagsForSection() {

    $section_terms = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];
    $section_term_ids = array_map(function ($term) {
      return $term->id();
    }, $section_terms);

    // Create some article terms.
    $term_1 = $this->createTerm($this->vocabulary);
    $term_2 = $this->createTerm($this->vocabulary);
    $term_3 = $this->createTerm($this->vocabulary);
    $term_4 = $this->createTerm($this->vocabulary);

    // And create some more terms.
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);
    $this->createTerm($this->vocabulary);

    // Create a section.
    $section = Section::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
      'field_tags' => $section_term_ids,
    ]);
    $section->save();

    $this->assertEquals($section_term_ids, array_keys($this->articleManager->loadAvailableTagsForSection($section)));

    // Create an article.
    $article_1_tags = array_merge($section_term_ids, [
      $term_1->id(),
      $term_2->id(),
    ]);
    $article_1 = Node::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'An article node',
      'status' => NodeInterface::PUBLISHED,
      'uid' => 0,
      'field_tags' => $article_1_tags,
    ]);
    $article_1->save();

    // Create another article.
    $article_2_tags = array_merge($section_term_ids, [
      $term_3->id(),
      $term_4->id(),
    ]);
    $article_2 = Node::create([
      'type' => self::ARTICLE_BUNDLE,
      'title' => 'An article node',
      'status' => NodeInterface::PUBLISHED,
      'uid' => 0,
      'field_tags' => $article_2_tags,
    ]);
    $article_2->save();

    $expected_term_ids = array_unique(array_merge($section_term_ids, $article_1_tags, $article_2_tags));
    sort($expected_term_ids);
    $this->assertEquals($expected_term_ids, array_keys($this->articleManager->loadAvailableTagsForSection($section)));

    $article_2->delete();
    $expected_term_ids = array_unique(array_merge($section_term_ids, $article_1_tags));
    sort($expected_term_ids);
    $this->assertEquals($expected_term_ids, array_keys($this->articleManager->loadAvailableTagsForSection($section)));
  }

}
