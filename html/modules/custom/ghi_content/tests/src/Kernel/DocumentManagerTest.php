<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\ghi_sections\Entity\Section;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\ghi_content\Traits\ContentTestTrait;

/**
 * Tests the document manager.
 *
 * @group ghi_content
 */
class DocumentManagerTest extends KernelTestBase {

  use TaxonomyTestTrait;
  use UserCreationTrait;
  use EntityReferenceFieldCreationTrait;
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
    'filter',
    'file',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'ghi_sections',
    'ghi_content',
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

    $this->documentManager = \Drupal::service('ghi_content.manager.document');

    NodeType::create(['type' => self::SECTION_BUNDLE])->save();
    NodeType::create(['type' => self::DOCUMENT_BUNDLE])->save();

    $this->vocabulary = $this->createVocabulary();

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', self::SECTION_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);
    $this->createEntityReferenceField('node', self::DOCUMENT_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests that tags can be imported.
   */
  public function testLoadNodesForSection() {

    $document_terms = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];

    $section_terms = [
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
      $this->createTerm($this->vocabulary),
    ];

    // Create documents.
    $document_1 = $this->createDocument([
      'title' => 'Document 1',
      'field_tags' => $this->mapTermsToFieldValue(array_merge($section_terms, $document_terms)),
    ]);
    $document_2 = $this->createDocument([
      'title' => 'Document 2',
      'field_tags' => $this->mapTermsToFieldValue(array_merge($section_terms, $document_terms)),
    ]);
    $this->createDocument();

    // Create a section with 2 documents.
    $section = Section::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
      'field_tags' => array_map(function (TermInterface $term) {
        return $term->id();
      }, $section_terms),
    ]);
    $section->save();

    $loaded_documents = array_values($this->documentManager->loadNodesForSection($section));
    $this->assertCount(2, $loaded_documents);
    $this->assertEquals($document_1->id(), $loaded_documents[0]->id());
    $this->assertEquals($document_2->id(), $loaded_documents[1]->id());

  }

  /**
   * Map an array of terms to an array of ids suitable for field values.
   *
   * @param \Drupal\taxonomy\TermInterface[] $terms
   *   An array of term objects.
   *
   * @return int[]
   *   An array of term ids.
   */
  private function mapTermsToFieldValue(array $terms) {
    return array_map(function (TermInterface $term) {
      return $term->id();
    }, $terms);
  }

}
