<?php

namespace Drupal\Tests\ghi_templates\Kernel;

use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;

/**
 * Tests the creation and validation of page template entities.
 *
 * @group ghi_templates
 */
class PageTemplateCreateTest extends KernelTestBase {

  use FieldTestTrait;
  use EntityReferenceFieldCreationTrait;
  use SectionTestTrait;
  use SubpageTestTrait;

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
    'text',
    'filter',
    'token',
    'path',
    'path_alias',
    'pathauto',
    'layout_builder',
    'layout_discovery',
    'hpc_api',
    'ghi_base_objects',
    'ghi_sections',
    'ghi_subpages',
    'ghi_templates',
  ];

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('base_object');
    $this->installEntitySchema('page_template');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);

    $this->entityTypeManager = $this->container->get('entity_type.manager');

    $this->createSubpageContentTypes();

    $this->createEntityReferenceField('page_template', 'page_template', 'field_entity_reference', 'Source page', 'node', 'default', [
      'target_bundles' => array_merge([SectionNodeInterface::BUNDLE], SubpageManager::SUPPORTED_SUBPAGE_TYPES),
    ]);
    $this->createEntityReferenceField('page_template', 'page_template', 'field_base_objects', 'Base objects', 'base_object', 'default');

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = \Drupal::service('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay('page_template', 'page_template');
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();
  }

  /**
   * Test that label is unique.
   */
  public function testUniqueLabel() {
    $section = $this->createSection();
    // Create a page template.
    $page_template = PageTemplate::create([
      'title' => '2023',
      'field_entity_reference' => ['target_id' => $section->id()],
    ]);
    $page_template->save();

    // Create another page template with the same name which should fail.
    $page_template = PageTemplate::create([
      'title' => '2023',
      'field_entity_reference' => ['target_id' => $section->id()],
    ]);
    $violations = $page_template->validate();
    $this->assertEquals(1, $violations->count());
  }

  /**
   * Test that templates from subpages are correctly created.
   */
  public function testSubpageTemplate() {
    // Create a section, which will automatically create subpages.
    $section = $this->createSection();
    $section_base_object = $section->getBaseObject();
    $this->assertInstanceOf(BaseObject::class, $section_base_object);

    // Get the created subpages.
    $existing_subpages = $this->entityTypeManager->getStorage('node')->loadByProperties([
      'type' => self::SUBPAGE_BUNDLES,
      'field_entity_reference' => $section->id(),
    ]);
    $this->assertNotEmpty($existing_subpages);
    $subpage = reset($existing_subpages);
    $this->assertInstanceOf(SubpageNodeInterface::class, $subpage);

    /** @var \Drupal\Core\Entity\EntityDisplayRepository $display_repository */
    $display_repository = $this->container->get('entity_display.repository');

    /** @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay $display */
    $display = $display_repository->getViewDisplay($subpage->getEntityTypeId(), $subpage->bundle());
    $display->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    // Use the first one to build a template.
    $page_template = PageTemplate::create([
      'title' => $subpage->label(),
      'field_entity_reference' => ['target_id' => $subpage->id()],
    ]);
    $page_template->save();

    // Assert that the base object has been set.
    $base_objects = $page_template->getBaseObjects();
    $this->assertNotEmpty($base_objects);
    $this->assertCount(1, $base_objects);

    $this->assertEquals($section_base_object, reset($base_objects));
  }

}
