<?php

namespace Drupal\Tests\ghi_templates\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_base_objects\Traits\FieldTestTrait;
use Drupal\Tests\ghi_sections\Traits\SectionTestTrait;
use Drupal\Tests\ghi_subpages\Traits\SubpageTestTrait;
use Drupal\ghi_base_objects\Entity\BaseObject;
use Drupal\ghi_blocks\Access\BlockPlacementAccess;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_sections\Entity\SectionNodeInterface;
use Drupal\ghi_subpages\Entity\SubpageNodeInterface;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the creation and validation of page template entities.
 */
#[Group('ghi_templates')]
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
    'layout_builder_restrictions',
    'migrate',
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
    $this->installConfig(['system', 'node', 'field', 'pathauto']);

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

  /**
   * Tests that initial snapshots retain restricted legacy components.
   */
  public function testRestrictedSourceComponent(): void {
    $display_repository = $this->container->get('entity_display.repository');
    $display = $display_repository->getViewDisplay('page_template', 'page_template');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'denylisted_blocks' => ['System' => ['system_powered_by_block']],
    ])->save();
    $source = $this->createSection();
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$component])]);
    $source->save();

    $page_template = PageTemplate::create([
      'title' => 'Template retaining a denylisted block',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->save();

    $sections = $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections();
    $this->assertCount(1, $sections);
    $copied = array_values($sections[0]->getComponents());
    $this->assertCount(1, $copied);
    $this->assertSame($component->get('configuration'), $copied[0]->get('configuration'));
    $this->assertNotSame($component->getUuid(), $copied[0]->getUuid());
    $this->assertCount(1, $source->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponents());

    // Initial copying is independent of the manual-addition restriction policy.
    $display->unsetThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction')->save();
    $page_template = PageTemplate::create([
      'title' => 'Template including a permitted block',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->save();
    $sections = $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections();
    $this->assertCount(1, $sections[0]->getComponents());
  }

  /**
   * Tests exported restrictions do not filter the initial layout snapshot.
   */
  public function testExportedRestrictionsRetainSectionCollection(): void {
    $this->enableModules(['file', 'hpc_common', 'hpc_downloads', 'ghi_form_elements', 'ghi_blocks']);
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->assertArrayHasKey('generic_link_carousel', $this->container->get('plugin.manager.block')->getDefinitions());
    $export_storage = new FileStorage(dirname(DRUPAL_ROOT) . '/config');
    $export = $export_storage->read('core.entity_view_display.page_template.page_template.default');
    $this->assertIsArray($export);
    $restrictions = $export['third_party_settings']['layout_builder_restrictions'];
    $display = $this->container->get('entity_display.repository')->getViewDisplay('page_template', 'page_template');
    foreach ($restrictions as $key => $value) {
      $display->setThirdPartySetting('layout_builder_restrictions', $key, $value);
    }
    $display->save();

    $source = $this->createSection();
    $components = [];
    foreach (['section_collection', 'generic_link_carousel'] as $plugin_id) {
      $components[] = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => $plugin_id]);
    }
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], $components)]);
    $source->save();
    $page_template = PageTemplate::create([
      'title' => 'Template retaining its section collection',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->save();

    $sections = $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections();
    $this->assertCount(1, $sections);
    $copied = array_values($sections[0]->getComponents());
    $this->assertCount(2, $copied);
    foreach ($copied as $delta => $component) {
      $this->assertSame($components[$delta]->getPluginId(), $component->getPluginId());
      $this->assertSame($components[$delta]->get('configuration'), $component->get('configuration'));
      $this->assertNotSame($components[$delta]->getUuid(), $component->getUuid());
    }
    $source_components = $source->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponents();
    $this->assertSame(['section_collection', 'generic_link_carousel'], array_values(array_map(static fn(SectionComponent $component) => $component->getPluginId(), $source_components)));
  }

  /**
   * Tests complete pending snapshots are cloned without changing the source.
   */
  public function testPendingLayoutSnapshot(): void {
    $source = $this->createSection();
    $saved_component = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$saved_component])]);
    $source->save();
    $source_values = $this->entityTypeManager->getStorage('node')->loadUnchanged($source->id())->toArray();
    $source_revision = $source->getRevisionId();

    $uuid = $this->container->get('uuid')->generate();
    $legacy = new SectionComponent($uuid, 'content', [
      'id' => 'uninstalled_legacy_block',
      'uuid' => $uuid,
      'lock_article' => TRUE,
      'sync' => ['enabled' => TRUE],
      'context_mapping' => ['custom' => 'original_provider'],
      'hpc' => ['items' => [['id' => 0, 'image' => [123], 'title' => 'Retained legacy row']]],
    ], ['locked' => TRUE, 'custom' => ['nested' => 'value']]);
    $legacy->setWeight(-8);
    $pending = [
      new Section('layout_onecol', ['label' => 'Pending first section'], [$legacy], ['ghi_templates' => ['custom' => TRUE]]),
      new Section('layout_onecol', ['label' => 'Pending second section'], [
        (new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']))->setWeight(12),
        (new SectionComponent($this->container->get('uuid')->generate(), 'content', ['legacy_setting' => 'Component without a plugin ID']))->setWeight(-4),
      ], ['ghi_templates' => ['second' => ['setting' => 'retained']]]),
    ];
    $expected = array_map(static fn(Section $section) => $section->toArray(), $pending);
    $page_template = PageTemplate::create([
      'title' => 'Template from pending layout',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $this->assertSame($page_template, $page_template->setSourceSections($pending));
    // Neither nested component changes nor section changes may alter the stored
    // snapshot after it has been supplied by the source editor.
    $legacy->setConfiguration(['id' => 'changed_after_snapshot']);
    $pending[0]->setLayoutSettings(['label' => 'Changed after snapshot']);
    $pending_before_save = array_map(static fn(Section $section) => $section->toArray(), $pending);
    $page_template->save();

    $copied = $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections();
    $this->assertCount(2, $copied);
    foreach ($copied as $delta => $section) {
      $actual = $section->toArray();
      $expected_components = array_values($expected[$delta]['components']);
      $actual_components = array_values($actual['components']);
      $this->assertCount(count($expected_components), $actual_components);
      foreach ($actual_components as $component_delta => $component) {
        $original = $expected_components[$component_delta];
        $this->assertNotSame($original['uuid'], $component['uuid']);
        $original['uuid'] = $component['uuid'];
        if (isset($original['configuration']['uuid'])) {
          $original['configuration']['uuid'] = $component['uuid'];
        }
        $this->assertSame($original, $component);
      }
      unset($actual['components'], $expected[$delta]['components']);
      $this->assertSame($expected[$delta], $actual);
    }
    $this->assertSame($pending_before_save, array_map(static fn(Section $section) => $section->toArray(), $pending));
    $reloaded_source = $this->entityTypeManager->getStorage('node')->loadUnchanged($source->id());
    $this->assertSame($source_revision, $reloaded_source->getRevisionId());
    $this->assertEquals($source_values, $reloaded_source->toArray());
    $this->assertSame($saved_component->toArray(), array_values($reloaded_source->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponents())[0]->toArray());
    // A later template save must not run setup a second time.
    $copied_values = array_map(static fn(Section $section) => $section->toArray(), $copied);
    $page_template->save();
    $this->assertSame($copied_values, array_map(static fn(Section $section) => $section->toArray(), $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections()));
  }

  /**
   * Tests an explicitly empty pending layout does not use the saved source.
   */
  public function testEmptyLayoutSnapshot(): void {
    $source = $this->createSection();
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol')]);
    $source->save();
    $page_template = PageTemplate::create([
      'title' => 'Template from empty pending layout',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->setSourceSections([])->save();
    $this->assertSame([], $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections());
    $this->assertCount(1, $source->get(OverridesSectionStorage::FIELD_NAME)->getSections());
  }

  /**
   * Tests node field blocks retain source-node rendering contexts.
   */
  public function testSourceNodeFieldContext(): void {
    $source = $this->createSection(['title' => 'Source field value']);
    $configuration = [
      'id' => 'field_block:node:section:title',
      'label' => 'Preserved field configuration',
      'formatter' => ['type' => 'string', 'label' => 'hidden', 'settings' => [], 'third_party_settings' => []],
      'context_mapping' => ['entity' => 'layout_builder.entity', 'view_mode' => 'view_mode'],
    ];
    $this->assertTrue($this->container->get('plugin.manager.block')->hasDefinition($configuration['id']));
    $source_component = new SectionComponent($this->container->get('uuid')->generate(), 'content', $configuration);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol', [], [$source_component])]);
    $source->save();
    $page_template = PageTemplate::create([
      'title' => 'Template preserving source field context',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->save();

    $copied = array_values($page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections()[0]->getComponents())[0];
    $configuration['context_mapping']['entity'] = 'node';
    $this->assertSame($configuration, $copied->get('configuration'));
    $contexts = [
      'layout_builder.entity' => EntityContext::fromEntity($page_template),
      'view_mode' => new Context(new ContextDefinition('string'), 'default'),
    ];
    ghi_templates_layout_builder_view_context_alter($contexts);
    $plugin = $copied->getPlugin($contexts);
    $this->assertSame($source->id(), $plugin->getContextValue('entity')->id());
    $this->assertSame('Source field value', $plugin->getContextValue('entity')->label());
    $this->assertSame('layout_builder.entity', $source_component->get('configuration')['context_mapping']['entity']);
  }

  /**
   * Tests source summaries still render after the origin has been deleted.
   */
  public function testDeletedSourceSummary(): void {
    $source = $this->createSection(['title' => 'Original template source']);
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol')]);
    $source->save();
    $page_template = PageTemplate::create([
      'title' => 'Template retaining a deleted origin',
      'field_entity_reference' => ['target_id' => $source->id()],
    ]);
    $page_template->save();
    $this->assertStringContainsString('Template based on <em>Original template source</em> section page', (string) $page_template->getSourceSummary());
    $source->delete();
    $page_template = $this->entityTypeManager->getStorage('page_template')->loadUnchanged($page_template->id());

    $this->assertNull($page_template->getSourceEntity());
    $this->assertStringContainsString('Template based on <em>deleted</em>', (string) $page_template->getSourceSummary());
    $this->assertStringStartsWith('deleted||', (string) $page_template->getSourceSummary('@source|@entity_type|@entity_type_lowercase'));
    $this->assertCount(1, $page_template->get(OverridesSectionStorage::FIELD_NAME)->getSections());
  }

  /**
   * Tests that the standard filtered definitions follow display restrictions.
   */
  public function testAllowedDefinitionsFollowConfiguration(): void {
    $lookup = AllowedBlockDefinitions::create($this->container);
    $display = $this->container->get('entity_display.repository')->getViewDisplay('node', 'section');
    $source = $this->createSection();
    $source->get(OverridesSectionStorage::FIELD_NAME)->setValue([new Section('layout_onecol')]);
    $source->save();
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', [
      'entity' => EntityContext::fromEntity($source),
    ]);
    $this->assertArrayHasKey('system_powered_by_block', $lookup->getDefinitions($storage));
    $this->assertTrue(BlockPlacementAccess::access($storage, 'system_powered_by_block')->isAllowed());
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'denylisted_blocks' => ['System' => ['system_powered_by_block']],
    ])->save();
    $this->assertArrayNotHasKey('system_powered_by_block', $lookup->getDefinitions($storage));
    $this->assertFalse(BlockPlacementAccess::access($storage, 'system_powered_by_block')->isAllowed());
    $display->unsetThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction')->save();
    $this->assertArrayHasKey('system_powered_by_block', $lookup->getDefinitions($storage));
  }

}
