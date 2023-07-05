<?php

namespace Drupal\Tests\ghi_sections\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\ghi_sections\Entity\Section;
use Drupal\ghi_sections\Menu\SectionMenuStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Base class for section menu tests.
 *
 * @group ghi_subpages_custom
 */
abstract class SectionMenuTestBase extends KernelTestBase {

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
    'token',
    'path_alias',
    'pathauto',
    'ghi_sections',
  ];

  const SECTION_BUNDLE = 'section';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The section menu item plugin manager.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuPluginManager
   */
  protected $sectionMenuPluginManager;

  /**
   * The section menu storage.
   *
   * @var \Drupal\ghi_sections\Menu\SectionMenuStorage
   */
  protected $sectionMenuStorage;

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

    $this->entityTypeManager = $this->container->get('plugin.manager.section_menu');
    $this->sectionMenuPluginManager = $this->container->get('plugin.manager.section_menu');
    $this->sectionMenuStorage = $this->container->get('ghi_sections.section_menu.storage');

    NodeType::create(['type' => self::SECTION_BUNDLE])->save();
    $this->sectionMenuStorage->addSectionMenuField(self::SECTION_BUNDLE);
    $this->assertTrue($this->bundleHasField(self::SECTION_BUNDLE, SectionMenuStorage::FIELD_NAME));

    // $this->setUpCurrentUser([], ['access content']);
    $this->setUpCurrentUser(['uid' => 1]);
  }

  /**
   * Check if a node bundle has a field.
   *
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return bool
   *   Returns a TRUE if the entity type has the field.
   */
  private function bundleHasField(string $bundle, string $field_name) {
    $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    return array_key_exists($field_name, $fields);
  }

  /**
   * Create a section reference field for the given bundle.
   *
   * @param string $bundle
   *   The bundle to which the reference field should be added.
   */
  protected function createSectionReferenceField($bundle) {
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
      'bundle' => $bundle,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => [
            self::SECTION_BUNDLE => self::SECTION_BUNDLE,
          ],
        ],
      ],
    ])->save();
  }

  /**
   * Create a section node.
   *
   * @return \Drupal\ghi_sections\Entity\Section
   *   A section node.
   */
  protected function createSection() {
    $section = Section::create([
      'type' => self::SECTION_BUNDLE,
      'title' => $this->randomString(),
      'uid' => 0,
    ]);
    $section->save();
    return $section;
  }

}
