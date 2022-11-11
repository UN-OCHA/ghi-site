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
 * @group ghi_documents
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
    'field',
    'entity_reference',
    'text',
    'filter',
    'ghi_documents',
  ];

  const SECTION_BUNDLE = 'section';
  const DOCUMENT_BUNDLE = 'document';

  /**
   * The document manager to test.
   *
   * @var \Drupal\ghi_documents\DocumentManager
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

    $this->documentManager = \Drupal::service('ghi_documents.manager');

    NodeType::create(['type' => self::SECTION_BUNDLE])->save();
    NodeType::create(['type' => self::DOCUMENT_BUNDLE])->save();

    // Setup the tags field on our node types.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_entity_reference',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
      'settings' => [
        'target_type' => 'node',
      ],
    ]);
    $field_storage->save();
    FieldConfig::create([
      'field_name' => 'field_entity_reference',
      'field_storage' => $field_storage,
      'bundle' => self::DOCUMENT_BUNDLE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            self::SECTION_BUNDLE => self::SECTION_BUNDLE,
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

    // Create a section.
    $section = Node::create([
      'type' => self::SECTION_BUNDLE,
      'title' => 'A section node',
      'uid' => 0,
    ]);
    $section->save();

    // Create documents.
    $document_1 = Node::create([
      'type' => self::DOCUMENT_BUNDLE,
      'title' => 'Document 1',
      'uid' => 0,
      'field_entity_reference' => [
        'target_id' => $section->id(),
      ],
    ]);
    $document_1->save();

    $document_2 = Node::create([
      'type' => self::DOCUMENT_BUNDLE,
      'title' => 'Document 2',
      'uid' => 0,
      'field_entity_reference' => [
        'target_id' => $section->id(),
      ],
    ]);
    $document_2->save();

    $this->assertEquals([$document_1->id(), $document_2->id()], array_keys($this->documentManager->loadNodesForSection($section)));

  }

}
