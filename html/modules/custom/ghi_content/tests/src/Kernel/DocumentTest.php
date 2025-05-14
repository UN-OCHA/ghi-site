<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\NodeType;
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

    NodeType::create(['type' => self::DOCUMENT_BUNDLE])->save();

    $this->vocabulary = $this->createVocabulary();

    // Setup the tags field on our node types.
    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
    ];
    $this->createEntityReferenceField('node', self::DOCUMENT_BUNDLE, 'field_tags', 'Tags', 'taxonomy_term', 'default', $handler_settings, FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED);

    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests Document::getSummary().
   */
  public function testGetSummary() {
    $this->createField('node', self::DOCUMENT_BUNDLE, 'text', 'field_summary', 'Summary');
    $document = $this->createDocument([
      'field_summary' => 'Summary',
    ]);
    $this->assertEquals('Summary', $document->getSummary());
  }

  /**
   * Tests Document::getCacheTags().
   */
  public function testGetCacheTags() {
    $document = $this->createDocument();
    $this->assertEquals(['node:' . $document->id()], $document->getCacheTags());
  }

}
