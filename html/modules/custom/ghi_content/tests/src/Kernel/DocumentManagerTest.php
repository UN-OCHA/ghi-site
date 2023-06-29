<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the document manager.
 *
 * @group ghi_content
 */
class DocumentManagerTest extends KernelTestBase {

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
    'ghi_content',
  ];

  const SECTION_BUNDLE = 'section';
  const DOCUMENT_BUNDLE = 'document';

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
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);

    $this->documentManager = \Drupal::service('ghi_content.manager.document');

    NodeType::create(['type' => self::SECTION_BUNDLE])->save();
    NodeType::create(['type' => self::DOCUMENT_BUNDLE])->save();

    // Setup the tags field on our node types.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_documents',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_name' => 'field_documents',
      'field_storage' => $field_storage,
      'bundle' => self::SECTION_BUNDLE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            self::DOCUMENT_BUNDLE => self::DOCUMENT_BUNDLE,
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
  public function testLoadNodesForSection() {

    // Create documents.
    $document_1 = $this->createDocument('Document 1');
    $document_2 = $this->createDocument('Document 2');
    $this->createDocument('Document 3');

    // Create a section with 2 documents.
    $section = Node::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
      'field_documents' => [
        [
          'target_id' => $document_1->id(),
        ],
        [
          'target_id' => $document_2->id(),
        ],
      ],
    ]);
    $section->save();

    $loaded_documents = $this->documentManager->loadNodesForSection($section);
    $this->assertCount(2, $loaded_documents);
    $this->assertEquals($document_1->id(), $loaded_documents[0]->id());
    $this->assertEquals($document_2->id(), $loaded_documents[1]->id());

  }

  /**
   * Create a document node with the given title.
   *
   * @param string $title
   *   The title of the document.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node object.
   */
  private function createDocument($title) {
    $document = Node::create([
      'type' => self::DOCUMENT_BUNDLE,
      'title' => $title,
      'uid' => 0,
    ]);
    $document->save();
    return $document;
  }

}
