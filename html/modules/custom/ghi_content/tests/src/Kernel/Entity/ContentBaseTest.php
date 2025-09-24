<?php

namespace Drupal\Tests\ghi_content\Kernel\Entity;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ghi_content\ContentManager\ArticleManager;
use Drupal\ghi_content\ContentManager\DocumentManager;
use Drupal\ghi_content\Controller\OrphanedContentController;
use Drupal\ghi_content\Entity\Article;
use Drupal\ghi_content\Entity\Document;
use Drupal\ghi_sections\Entity\Section;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Tests some features of the abstract ContentBase class.
 *
 * @group ghi_content
 */
class ContentBaseTest extends KernelTestBase {

  use TaxonomyTestTrait;
  use FieldTestTrait;
  use EntityReferenceFieldCreationTrait;
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
  ];

  const ARTICLE_BUNDLE = 'article';
  const SECTION_BUNDLE = 'section';

  /**
   * A vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $vocabulary;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'file', 'pathauto']);

    NodeType::create(['type' => self::ARTICLE_BUNDLE])->save();
    NodeType::create(['type' => self::SECTION_BUNDLE])->save();

    $this->vocabulary = $this->createVocabulary();

    // Now create the orphaned field and re-create the article.
    $this->createField('taxonomy_term', $this->vocabulary->id(), 'boolean', 'field_structural_tag', 'Structural tag');

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', self::SECTION_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->createEntityReferenceField('node', self::ARTICLE_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
  }

  /**
   * Tests that nodes that have been manually unpublished can be identified.
   */
  public function testUnpublishedManually() {
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $article->save();
    $this->assertTrue($article->unpublishedManually());

    $article->setNewRevision();
    $article->save();
    $this->assertFalse($article->unpublishedManually());

    $article->setPublished();
    $article->setNewRevision();
    $article->save();
    $this->assertFalse($article->unpublishedManually());

    $article->setUnpublished();
    $article->setNewRevision();
    $article->save();
    $this->assertTrue($article->unpublishedManually());
  }

  /**
   * Test logic around the orphaned field.
   */
  public function testOrphanedField() {
    // Create a node without an orphaned field.
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $article->save();

    // Assert that calling setOrphaned and isOrphaned is not creating errors.
    $article->setOrphaned(TRUE);
    $article->save();
    $this->assertFalse($article->isOrphaned());

    // Now create the orphaned field and re-create the article.
    $this->createField('node', self::ARTICLE_BUNDLE, 'boolean', OrphanedContentController::FIELD_NAME, 'Orphaned');
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $article->save();

    // Confirm we can set and unset the orphaned flag.
    $article->setOrphaned(TRUE)->save();
    $this->assertTrue($article->isOrphaned());

    $article->setOrphaned(FALSE)->save();
    $this->assertFalse($article->isOrphaned());

    // Confirm access to orphaned articles. Even assuming a user who can bypass
    // node access, we want to impose some limits on what can be done.
    $article->setOrphaned(TRUE)->save();
    $this->assertTrue($article->isOrphaned());
    $this->setUpCurrentUser([], [
      'access content',
      'bypass node access',
    ]);
    $this->assertFalse($article->access('update'));
    $this->assertTrue($article->access('view'));
    $this->assertTrue($article->access('delete'));
  }

  /**
   * Test the logic for the short title.
   */
  public function testShortTitle() {
    // Create the short title field and an article that uses it.
    $this->createField('node', self::ARTICLE_BUNDLE, 'string', 'field_short_title', 'Short title');
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
      'field_short_title' => 'Short title',
    ]);
    $article->save();

    // Confirm that we can retrieve it.
    $this->assertEquals('Short title', $article->getShortTitle());

    // Confirm it's used in links.
    $link = $article->toLink();
    $this->assertEquals('Short title', $link->getText());

    // Confirm we can still override the link text.
    $link = $article->toLink('Another title');
    $this->assertEquals('Another title', $link->getText());
  }

  /**
   * Test getting the content manager.
   */
  public function testGetContentManager() {
    $article = Article::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->assertInstanceOf(ArticleManager::class, $article->getContentManager());

    $document = Document::create([
      'title' => 'Title',
      'status' => NodeInterface::NOT_PUBLISHED,
    ]);
    $this->assertInstanceOf(DocumentManager::class, $document->getContentManager());
  }

  /**
   * Test sections as context nodes.
   */
  public function testContextNodeSection() {
    // Create some tags shared by the section and the article.
    $common_tags = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];

    // Create a published section and a published article.
    $section = Section::create([
      'title' => 'Section title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => $common_tags,
    ]);
    $section->save();
    $article = Article::create([
      'title' => 'Article title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => $common_tags,
    ]);
    $article->save();

    // They are not tied together yet, so the article should show it's own
    // label as the page title.
    $this->assertEquals('Article title', $article->getPageTitle());

    // Now set the section as the current context for the article.
    $this->assertTrue($article->isValidContextNode($section));
    $article->setContextNode($section);
    $this->assertEquals($section, $article->getContextNode());

    // And confirm that the article returns the label of the section node now
    // for the page title.
    $this->assertEquals('Section title', $article->getPageTitle());

    // Create another section with it's own set of tags that are not shared
    // with the article.
    $other_section = Section::create([
      'title' => 'Section title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => [
        $this->createTerm($this->vocabulary),
        $this->createTerm($this->vocabulary),
      ],
    ]);
    $other_section->save();

    // Confirm that this is not a valid context for the article.
    $this->assertFalse($article->isValidContextNode($other_section));
    $article->setContextNode($other_section);
    $this->assertNull($article->getContextNode());

    // Create another article without any tags and confirm it is not a valid
    // context for the article.
    $other_article = Article::create([
      'title' => 'Other article title',
      'status' => NodeInterface::PUBLISHED,
    ]);
    $other_article->save();
    $this->assertFalse($article->isValidContextNode($other_article));
  }

  /**
   * Test documents as context nodes.
   */
  public function testContextNodeDocument() {
    // Create a published article with some tags.
    $article = Article::create([
      'title' => 'Article title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => [
        $this->createTerm($this->vocabulary),
        $this->createTerm($this->vocabulary),
      ],
    ]);
    $article->save();
    $this->assertEquals('Article title', $article->getPageTitle());

    // Store the url of the standalone article to compare later.
    $article_url = $article->toUrl()->toString();
    $this->assertEquals('/node/1', $article_url);

    // Mock a document. Using a real document is more complicated because it
    // would check for the presence of an article in any of the remote chapters
    // of the document.
    $document = $this->prophesize(Document::class);
    $document->hasArticle($article)->willReturn(TRUE);
    $document->label()->willReturn('Document title');
    $document_url = $this->prophesize(Url::class);
    $document_url->toString()->willReturn('/document/1');
    $document->toUrl()->willReturn($document_url->reveal());

    // Confirm this document is a valid context node and that it's label is
    // used as the page title for the article.
    $this->assertTrue($article->isValidContextNode($document->reveal()));
    $article->setContextNode($document->reveal());
    $this->assertEquals($document->reveal(), $article->getContextNode());
    $this->assertEquals('Document title', $article->getPageTitle());

    // Not get the url with the context node set.
    $url = $article->toUrl();
    $this->assertEquals('/document/1' . $article_url, $url->toString());
    // Confirm it's marked as "alias" already, so that the url stays as is.
    $this->assertTrue($url->getOption('alias'));
    // And that a custom_path option is set so that ContentPagePathProcessor
    // can tell that this should be url to be used in rendered links. Otherwise
    // link sanitization done by drupal would reset the custom set path.
    $this->assertEquals('/document/1' . $article_url, $url->getOption('custom_path'));

    // Mock another document.
    $document = $this->prophesize(Document::class);
    $document->hasArticle($article)->willReturn(FALSE);
    $document->label()->willReturn('Document title');

    // Confirm this document is a not a valid context node and that it's label
    // is not used as the page title for the article.
    $this->assertFalse($article->isValidContextNode($document->reveal()));
    $article->setContextNode($document->reveal());
    $this->assertNull($article->getContextNode());
    $this->assertEquals('Article title', $article->getPageTitle());
  }

  /**
   * Tests that tags can be retrieved.
   */
  public function testGetTags() {
    // Create some common tags to be shared between article and section.
    $common_tags = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];

    // Create an article with the common tags and some more.
    $article = Article::create([
      'title' => 'Article title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => array_merge($common_tags, [
        $this->createTerm($this->vocabulary),
        $this->createTerm($this->vocabulary),
      ]),
    ]);
    $article->save();

    $this->assertCount(5, $article->getTags());

    // Create a section with the common tags.
    $section = Section::create([
      'title' => 'Section title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => $common_tags,
    ]);
    $section->save();

    // Confirm the section is indeed a valid context and set it.
    $this->assertTrue($article->isValidContextNode($section));
    $article->setContextNode($section);
    $this->assertEquals($section, $article->getContextNode());

    // Make sure that the article retrieves all it's tags both when fetching
    // the standalone tags and the tags including the context node's tags.
    // They are equal, because the section tags must be an exact subset of the
    // article tags, otherwise the article would not be part of the sections
    // article universe.
    $this->assertCount(5, $article->getTags(TRUE));
    $this->assertCount(5, $article->getTags());

    // Now mock a document that pretends to have that article in it's chapters,
    // has a couple of specific tags and a title.
    $document = $this->prophesize(Document::class);
    $document->hasArticle($article)->willReturn(TRUE);
    $document->label()->willReturn('Document title');
    $document->getTags()->willReturn([
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ]);

    // Confirm it's a valid context node for the article.
    $this->assertTrue($article->isValidContextNode($document->reveal()));
    $article->setContextNode($document->reveal());
    $this->assertEquals($document->reveal(), $article->getContextNode());

    // Confirm that the document has the 2 tags it's be setup with.
    $this->assertCount(2, $document->reveal()->getTags());
    $this->assertCount(2, $article->getContextNode()->getTags());

    // And confirm that the article can fetch it's own standalone tags, but
    // also the tags including any tags from the context node.
    $this->assertCount(7, $article->getTags(TRUE));
    $this->assertCount(5, $article->getTags());

    // Now do that again, but give the document and the article one tag that
    // they share.
    $document->getTags()->willReturn([
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $common_tags[0],
    ]);
    $this->assertTrue($article->isValidContextNode($document->reveal()));
    $article->setContextNode($document->reveal());
    $this->assertEquals($document->reveal(), $article->getContextNode());

    // Confirm that the document has the 2 tags it's be setup with.
    $this->assertCount(3, $document->reveal()->getTags());
    $this->assertCount(3, $article->getContextNode()->getTags());

    // And confirm that the tags are unique when combining the article tags and
    // the document context tags.
    $this->assertCount(7, $article->getTags(TRUE));
  }

  /**
   * Tests that structural tags are handled and kept out of displayed tags.
   */
  public function testStructuralTags() {
    // Create some normal tags.
    $tags = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];
    // Create some structural tags.
    $structural_tags = [
      $this->createTerm($this->vocabulary, ['field_structural_tag' => TRUE]),
      $this->createTerm($this->vocabulary, ['field_structural_tag' => TRUE]),
    ];

    // Create an article having all 5 tags.
    $article = Article::create([
      'title' => 'Article title',
      'status' => NodeInterface::PUBLISHED,
      'field_tags' => array_merge($tags, $structural_tags),
    ]);
    $article->save();

    // Confirm all tags are returned by getTags.
    $this->assertCount(count($tags) + count($structural_tags), $article->getTags());
    // Confirm only structural tags are returned by getStructuralTags.
    $this->assertCount(count($structural_tags), $article->getStructuralTags());
    // Confirm a max number of item is returned by getDisplayTags.
    $this->assertCount(6, $article->getDisplayTags());
    // Confirm that the number of item returned by getDisplayTags can be set.
    $this->assertCount(3, $article->getDisplayTags(3));

    // Confirm the result of getDisplayTags is an array of tag names, keyed by
    // secuential integers.
    $expected_display_tags_all = array_map(function (Term $tag) {
      return $tag->label();
    }, $tags);
    $this->assertEquals(array_slice($expected_display_tags_all, 0, 6), $article->getDisplayTags());
    $this->assertEquals(array_slice($expected_display_tags_all, 0, 2), $article->getDisplayTags(2));
  }

  /**
   * Test assembly of page meta data.
   */
  public function testPageMetaData() {
    $article = Article::create([
      'title' => 'Article title',
      'status' => NodeInterface::PUBLISHED,
      'created' => strtotime('2024-06-01 UTC'),
      'field_tags' => [
        $this->createTerm($this->vocabulary, ['name' => 'Tag 1']),
        $this->createTerm($this->vocabulary, ['name' => 'Tag 2']),
      ],
    ]);
    $article->save();

    $metadata = $article->getPageMetaData(FALSE);
    $this->assertIsArray($metadata);
    $this->assertCount(2, $metadata);
    $this->assertEquals('Published on 1 June 2024', (string) $metadata[0]['#markup']);
    $this->assertEquals('Keywords Tag 1, Tag 2', (string) $metadata[1]['#markup']);

    $metadata = $article->getPageMetaData();
    $this->assertIsArray($metadata);
    $this->assertCount(3, $metadata);
    $this->assertEquals('social_links', $metadata[2]['#theme']);

    $article->setUnpublished()->save();
    $metadata = $article->getPageMetaData();
    $this->assertIsArray($metadata);
    $this->assertCount(2, $metadata);
  }

  /**
   * Test protection state and access.
   */
  public function testProtection() {
    $article = Article::create();
    $this->assertFalse($article->isProtected());
    $this->assertTrue($article->protectedAccess());
  }

}
