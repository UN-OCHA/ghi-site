<?php

namespace Drupal\Tests\ghi_templates\Kernel;

use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\ghi_blocks\Access\BlockPlacementAccess;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\ghi_templates\Plugin\LayoutBuilderRestriction\TemplateSourceRestriction;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests source-scoped template additions through the real restriction pipeline.
 */
#[Group('ghi_templates')]
class TemplateSourceRestrictionTest extends KernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'block_content',
    'layout_discovery',
    'layout_builder',
    'layout_builder_restrictions',
    'ghi_templates',
  ];

  /**
   * The restriction plugin under test.
   */
  protected TemplateSourceRestriction $restriction;

  /**
   * The shared restriction lookup.
   */
  protected AllowedBlockDefinitions $allowedDefinitions;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'node', 'page_template', 'block_content'] as $entity_type) {
      $this->installEntitySchema($entity_type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);
    $this->createEntityReferenceField('page_template', 'page_template', 'field_entity_reference', 'Source page', 'node');

    foreach (['homepage', 'section'] as $bundle) {
      NodeType::create(['type' => $bundle, 'name' => $bundle])->save();
      $this->createDisplay('node', $bundle);
    }
    $this->createDisplay('page_template', 'page_template');
    foreach (['basic', 'notice'] as $bundle) {
      BlockContentType::create(['id' => $bundle, 'label' => $bundle])->save();
    }
    $this->container->get('plugin.manager.block')->clearCachedDefinitions();
    $this->restriction = $this->container->get('plugin.manager.layout_builder_restriction')->createInstance('ghi_template_source');
    $this->allowedDefinitions = AllowedBlockDefinitions::create($this->container);
  }

  /**
   * Tests origin bundles and changed restrictions on existing copies.
   */
  public function testSourcePoliciesRemainDynamic(): void {
    $homepage = $this->createNode('homepage');
    $section = $this->createNode('section');
    $homepage_storage = $this->createTemplateStorage($homepage);
    $section_storage = $this->createTemplateStorage($section);
    $display = LayoutBuilderEntityViewDisplay::load('node.section.default');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'restricted_categories' => ['System'],
    ])->save();

    $this->assertArrayHasKey('system_powered_by_block', $this->allowedDefinitions->getDefinitions($homepage_storage));
    $this->assertArrayNotHasKey('system_powered_by_block', $this->allowedDefinitions->getDefinitions($section_storage));
    $this->assertTrue(BlockPlacementAccess::access($homepage_storage, 'system_powered_by_block')->isAllowed());
    $this->assertFalse(BlockPlacementAccess::access($section_storage, 'system_powered_by_block')->isAllowed());

    $display->unsetThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction')->save();
    $this->assertArrayHasKey('system_powered_by_block', $this->allowedDefinitions->getDefinitions($section_storage));

    $display = LayoutBuilderEntityViewDisplay::load('node.homepage.default');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'denylisted_blocks' => ['System' => ['system_powered_by_block']],
    ])->save();
    $this->assertArrayNotHasKey('system_powered_by_block', $this->allowedDefinitions->getDefinitions($homepage_storage));
  }

  /**
   * Tests inline selection obeys the same origin policy as direct additions.
   */
  public function testInlineBlocksFollowSource(): void {
    $display = LayoutBuilderEntityViewDisplay::load('node.homepage.default');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'allowlisted_blocks' => ['Inline blocks' => ['inline_block:basic']],
    ])->save();
    $display = LayoutBuilderEntityViewDisplay::load('node.section.default');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'restricted_categories' => ['Inline blocks'],
    ])->save();
    $homepage_storage = $this->createTemplateStorage($this->createNode('homepage'));
    $section_storage = $this->createTemplateStorage($this->createNode('section'));

    $this->assertSame(['inline_block:basic'], $this->restriction->inlineBlocksAllowedinContext($homepage_storage, 0, 'content'));
    $this->assertSame([], $this->restriction->inlineBlocksAllowedinContext($section_storage, 0, 'content'));
    $this->assertTrue(BlockPlacementAccess::access($homepage_storage, 'inline_block:basic')->isAllowed());
    $this->assertFalse(BlockPlacementAccess::access($homepage_storage, 'inline_block:notice')->isAllowed());
    $this->assertFalse(BlockPlacementAccess::access($section_storage, 'inline_block:basic')->isAllowed());
  }

  /**
   * Tests missing origins deny additions without changing components.
   */
  public function testMissingOriginFailsClosed(): void {
    $storage = $this->createTemplateStorage(NULL);
    $before = $storage->getSections()[0]->toArray();

    $this->assertSame([], $this->allowedDefinitions->getDefinitions($storage));
    $this->assertSame([], $this->restriction->inlineBlocksAllowedinContext($storage, 0, 'content'));
    $this->assertFalse(BlockPlacementAccess::access($storage, 'system_powered_by_block')->isAllowed());
    $this->assertSame($before, $storage->getSections()[0]->toArray());
  }

  /**
   * Tests ordinary node layouts and shared display contexts remain unchanged.
   */
  public function testOtherAndDefaultContextsAreUnchanged(): void {
    $node = $this->createNode('homepage');
    $storage_manager = $this->container->get('plugin.manager.layout_builder.section_storage');
    $node_storage = $storage_manager->load('overrides', ['entity' => EntityContext::fromEntity($node)]);
    $display_storage = $storage_manager->load('defaults', [
      'display' => EntityContext::fromEntity(LayoutBuilderEntityViewDisplay::load('page_template.page_template.default')),
    ]);
    $definitions = ['already_filtered' => ['admin_label' => 'Existing selection']];
    foreach ([$node_storage, $display_storage] as $storage) {
      $this->assertSame($definitions, $this->restriction->alterBlockDefinitions($definitions, ['section_storage' => $storage]));
      $this->assertSame(['inline_block:basic', 'inline_block:notice'], $this->restriction->inlineBlocksAllowedinContext($storage, 0, 'content'));
    }
    $this->assertSame($definitions, $this->restriction->alterBlockDefinitions($definitions, []));
  }

  /**
   * Tests the origin cannot reintroduce a definition removed by another filter.
   */
  public function testPriorFiltersArePreserved(): void {
    $storage = $this->createTemplateStorage($this->createNode('homepage'));
    $definitions = $this->container->get('plugin.manager.block')->getDefinitions();
    $filtered = ['system_branding_block' => $definitions['system_branding_block']];

    $this->assertSame($filtered, $this->restriction->alterBlockDefinitions($filtered, ['section_storage' => $storage]));
  }

  /**
   * Tests copied legacy blocks can still move after the origin disallows them.
   */
  public function testGrandfatheredComponentsCanMove(): void {
    $storage = $this->createTemplateStorage($this->createNode('homepage'));
    $display = LayoutBuilderEntityViewDisplay::load('node.homepage.default');
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'restricted_categories' => ['System'],
    ])->save();
    $before = $storage->getSections()[0]->toArray();
    $uuid = array_key_first($storage->getSection(0)->getComponents());

    $this->assertArrayNotHasKey('system_powered_by_block', $this->allowedDefinitions->getDefinitions($storage));
    $manager = $this->container->get('plugin.manager.layout_builder_restriction');
    foreach (array_keys($manager->getSortedPlugins()) as $id) {
      $this->assertTrue($manager->createInstance($id)->blockAllowedinContext($storage, 0, 0, 'content', $uuid));
    }
    $this->assertSame($before, $storage->getSections()[0]->toArray());
  }

  /**
   * Creates an overridable display with neutral block and fixed layout rules.
   */
  private function createDisplay(string $entity_type, string $bundle): void {
    $display = $this->container->get('entity_display.repository')->getViewDisplay($entity_type, $bundle);
    $display->enableLayoutBuilder()->setOverridable()->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'allowed_layouts' => ['layout_onecol'],
      'allowlisted_blocks' => [],
      'denylisted_blocks' => [],
      'restricted_categories' => [],
    ])->save();
  }

  /**
   * Creates a real origin node without adding editorial layout components.
   */
  private function createNode(string $bundle): NodeInterface {
    $node = Node::create(['type' => $bundle, 'title' => $bundle]);
    $node->save();
    return $node;
  }

  /**
   * Creates storage for a template containing a grandfathered component.
   */
  private function createTemplateStorage(?NodeInterface $source): SectionStorageInterface {
    $template = PageTemplate::create([
      'title' => 'Template',
      'field_entity_reference' => $source,
    ]);
    $component = new SectionComponent($this->container->get('uuid')->generate(), 'content', ['id' => 'system_powered_by_block']);
    $template->get('layout_builder__layout')->setValue([new Section('layout_onecol', [], [$component])]);
    return $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($template)]);
  }

}
