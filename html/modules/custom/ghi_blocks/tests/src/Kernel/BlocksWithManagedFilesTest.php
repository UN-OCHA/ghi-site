<?php

namespace Drupal\Tests\ghi_content\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\file\Entity\File;
use Drupal\ghi_image\CropManager;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Tests aspects of the block logic.
 *
 * @group ghi_blocks
 */
class BlocksWithManagedFilesTest extends KernelTestBase {

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
    'layout_builder',
    'text',
    'filter',
    'file',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'hpc_api',
    // 'hpc_common',
    'ghi_form_elements',
    'ghi_subpages',
    'ghi_sections',
    'ghi_blocks',
  ];

  const BUNDLE = 'page';

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
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['system', 'node', 'taxonomy', 'field', 'file', 'pathauto']);

    // Mock the crop manager.
    $crop_manager = $this->prophesize(CropManager::class);
    $this->container->set('ghi_image.crop_manager', $crop_manager->reveal());
    \Drupal::setContainer($this->container);

    $node_type = NodeType::create(['type' => self::BUNDLE]);
    $node_type->save();

    /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('node', self::BUNDLE);
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // $this->setUpCurrentUser([], ['access content']);
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Tests that files are correctly stored and cleaned up.
   */
  public function testManagedFileStorageInBlocks() {
    // Create a node.
    $node = Node::create([
      'type' => self::BUNDLE,
      'title' => $this->randomString(),
    ]);
    $node->save();

    // A new file is temporary.
    $file = $this->createFile();
    $this->assertFalse($file->isPermanent());

    // Add a \Drupal\ghi_blocks\Plugin\Block\Generic\Links block to the node.
    // That plugin uses ManagedFileBlockTrait to persist and cleanup uploaded
    // files.
    $component_uuid = $this->addLinkBlockWithFileToNode($node, $file);
    $node->save();

    // After saving the node, the file should now have been persisted.
    $file = File::load($file->id());
    $this->assertTrue($file->isPermanent());

    // Now we remove the block from the node.
    /** @var \Drupal\layout_builder\SectionListInterface $sections */
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $sections[0]['section']->removeComponent($component_uuid);
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    $node->save();

    // And that should also delete the file.
    $file = File::load($file->id());
    $this->assertNull($file);
  }

  /**
   * Tests that uploaded files are not deleted when revisions are upated.
   */
  public function testFilesAreNotDeletedByRevisionUpdates() {
    // Create a node.
    $node = Node::create([
      'type' => self::BUNDLE,
      'title' => $this->randomString(),
    ]);
    $node->save();

    // Create a new revision.
    $node->setNewRevision();
    $node->save();

    // A new file is temporary.
    $file = $this->createFile();
    $this->assertFalse($file->isPermanent());

    // Add a \Drupal\ghi_blocks\Plugin\Block\Generic\Links block to the node.
    // That plugin uses ManagedFileBlockTrait to persist and cleanup uploaded
    // files.
    $this->addLinkBlockWithFileToNode($node, $file);
    $node->setNewRevision();
    $node->save();

    // Confirm the file is there and made permanent.
    $file = File::load($file->id());
    $this->assertNotNull($file);
    $this->assertTrue($file->isPermanent());

    // Now go over all revisions and update them. This also updates revisions
    // that didn't have the link block and thus didn't have any files uploaded.
    // We basically test here that LayoutBuilderBlockController::updateEntity()
    // only processes an entity update if the default revision is updated.
    /** @var \Drupal\node\NodeStorageInterface $node_storage */
    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');
    foreach ($node_storage->revisionIds($node) as $revision_id) {
      // Update the revision.
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $node_storage->loadRevision($revision_id);
      $revision->setNewRevision(FALSE);
      $revision->setSyncing(TRUE);
      $revision->save();

      // And confirm that the file still exists and that it is still permanent.
      $file = File::load($file->id());
      $this->assertNotNull($file);
      $this->assertTrue($file->isPermanent());
    }
  }

  /**
   * Add a link block plugin using $file as a section component to the node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to which the block should be added.
   * @param \Drupal\file\Entity\File $file
   *   The file that the block should use.
   *
   * @return string
   *   The UUID of the component.
   */
  private function addLinkBlockWithFileToNode($node, $file) {
    $configuration = [
      'id' => 'links',
      'label' => '<none>',
      'label_display' => FALSE,
      'provider' => 'ghi_blocks',
      'hpc' => [
        'links' => [
          'links' => [
            [
              'id' => 1,
              'item_type' => 'link',
              'config' => [
                'label' => 'Test link with image',
                'link' => [
                  'link' => [
                    'label' => NULL,
                    'link_type' => 'custom',
                    'link_custom' => [
                      'url' => 'https://google.com',
                    ],
                    'link_related' => [
                      'target' => NULL,
                    ],
                  ],
                ],
                'image' => [
                  'image' => [$file->id()],
                ],
                'content' => [
                  'date' => '2024-05-22',
                  'description' => [
                    'value' => '',
                    'format' => 'wysiwyg_simple',
                  ],
                  'description_toggle' => 0,
                ],
              ],
            ],
          ],
        ],
      ],
    ];

    // Get the sections.
    $sections = $node->get(OverridesSectionStorage::FIELD_NAME)->getValue() ?: [
      0 => ['section' => new Section('layout_onecol', [], [])],
    ];
    // Add a new component to the section with delta 0.
    $component = new SectionComponent('10000000-0000-1000-a000-000000000000', 'content', $configuration);
    $sections[0]['section']->appendComponent($component);

    // Store the modified sections in the node.
    $node->get(OverridesSectionStorage::FIELD_NAME)->setValue($sections);
    return $component->getUuid();
  }

  /**
   * Create a file object.
   *
   * @return \Drupal\file\Entity\File
   *   The created file.
   */
  private function createFile() {
    // Create a test file object.
    $file = File::create([
      'fid' => 1,
      'filename' => 'test.png',
      'filesize' => 100,
      'uri' => 'public://images/test.png',
      'filemime' => 'image/png',
    ]);
    $file->save();
    $this->assertFalse($file->isPermanent());
    return $file;
  }

}
