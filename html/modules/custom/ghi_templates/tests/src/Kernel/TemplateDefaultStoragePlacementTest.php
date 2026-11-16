<?php

namespace Drupal\Tests\ghi_templates\Kernel;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\ghi_blocks\LayoutBuilder\AllowedBlockDefinitions;
use Drupal\ghi_templates\Entity\PageTemplate;
use Drupal\ghi_templates\LayoutBuilder\ApplyPageTemplateForm;
use Drupal\ghi_templates\LayoutBuilder\ImportPageConfigForm;
use Drupal\KernelTests\KernelTestBase;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder\OverridesSectionStorageInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests entity-specific restrictions when imports start from shared defaults.
 */
#[Group('ghi_templates')]
class TemplateDefaultStoragePlacementTest extends KernelTestBase {

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
    'layout_discovery',
    'layout_builder',
    'layout_builder_restrictions',
    'ghi_templates',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['user', 'node', 'page_template'] as $entity_type) {
      $this->installEntitySchema($entity_type);
    }
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'node', 'field']);
    $this->createEntityReferenceField('page_template', 'page_template', 'field_entity_reference', 'Source page', 'node');
    NodeType::create(['type' => 'page', 'name' => 'Page'])->save();
    foreach (['node' => 'page', 'page_template' => 'page_template'] as $entity_type => $bundle) {
      $display = $this->container->get('entity_display.repository')->getViewDisplay($entity_type, $bundle);
      $display->enableLayoutBuilder()->setOverridable()->save();
      $display->removeAllSections()->appendSection(new Section('layout_onecol'));
      $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
        'allowed_layouts' => ['layout_onecol'],
        'allowlisted_blocks' => [],
        'denylisted_blocks' => [],
        'restricted_categories' => [],
      ])->save();
    }
  }

  /**
   * Tests a neutral display cannot bypass an instance's source restrictions.
   */
  #[DataProvider('unavailableSourceProvider')]
  public function testTemplateDestinationCannotBypassSourcePolicy(bool $source_exists): void {
    $source = NULL;
    if ($source_exists) {
      $source = Node::create(['type' => 'page', 'title' => 'Origin']);
      $source->save();
      LayoutBuilderEntityViewDisplay::load('node.page.default')->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
        'restricted_categories' => ['System'],
      ])->save();
    }
    $template = PageTemplate::create(['title' => 'Destination', 'field_entity_reference' => $source]);
    $template->save();
    $display = LayoutBuilderEntityViewDisplay::load('page_template.page_template.default');
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('defaults', ['display' => EntityContext::fromEntity($display)]);
    $before_defaults = $storage->getSection(0)->toArray();
    $before_destination = $template->get(OverridesSectionStorage::FIELD_NAME)->getValue();
    $lookup = AllowedBlockDefinitions::create($this->container);

    $this->assertArrayHasKey('system_powered_by_block', $lookup->getDefinitions($storage));
    $this->assertArrayNotHasKey('system_powered_by_block', $lookup->getDefinitions($storage, $template));
    try {
      $this->container->get('ghi_templates.manager')->buildSectionStorageFromPageConfig($storage, $template, $this->createPageConfig(), TRUE, ['0--component' => '0--component']);
      $this->fail('The restricted component was accepted.');
    }
    catch (\InvalidArgumentException $exception) {
      $this->assertSame('This element is not allowed in this layout.', $exception->getMessage());
    }
    $this->assertSame($before_defaults, $storage->getSection(0)->toArray());
    $this->assertSame($before_destination, $template->get(OverridesSectionStorage::FIELD_NAME)->getValue());
  }

  /**
   * Provides origins that do not permit the selected component.
   */
  public static function unavailableSourceProvider(): array {
    return [
      'missing origin' => [FALSE],
      'restricted origin' => [TRUE],
    ];
  }

  /**
   * Tests normal imports into node default layouts still produce overrides.
   */
  public function testNodeDefaultImportRemainsAvailable(): void {
    $node = Node::create(['type' => 'page', 'title' => 'Destination']);
    $node->save();
    $display = LayoutBuilderEntityViewDisplay::load('node.page.default');
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('defaults', ['display' => EntityContext::fromEntity($display)]);
    $this->assertSame([], $storage->getSection(0)->getComponents());
    $this->assertTrue($node->get(OverridesSectionStorage::FIELD_NAME)->isEmpty());
    $lookup = AllowedBlockDefinitions::create($this->container);
    $this->assertArrayHasKey('system_powered_by_block', $lookup->getDefinitions($storage, $node));
    $this->assertTrue($node->get(OverridesSectionStorage::FIELD_NAME)->isEmpty());

    $result = $this->container->get('ghi_templates.manager')->buildSectionStorageFromPageConfig($storage, $node, $this->createPageConfig(), FALSE, ['0--component' => '0--component']);

    $this->assertInstanceOf(OverridesSectionStorageInterface::class, $result);
    $components = array_values($result->getSection(0)->getComponents());
    $this->assertCount(1, $components, implode(', ', array_map(static fn(SectionComponent $component) => $component->getPluginId(), $components)));
    $this->assertSame('system_powered_by_block', $components[0]->getPluginId());
    $this->container->get('entity_type.manager')->getStorage('node')->resetCache([$node->id()]);
    $this->assertTrue(Node::load($node->id())->get(OverridesSectionStorage::FIELD_NAME)->isEmpty());
  }

  /**
   * Tests a destination that cannot have overrides does not gain definitions.
   */
  public function testUnsupportedDestinationFailsClosed(): void {
    $display = LayoutBuilderEntityViewDisplay::load('node.page.default');
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('defaults', ['display' => EntityContext::fromEntity($display)]);

    $this->assertSame([], AllowedBlockDefinitions::create($this->container)->getDefinitions($storage, NodeType::load('page')));
  }

  /**
   * Tests both workflows build the same safe, policy-aware confirmation table.
   */
  #[DataProvider('confirmationDestinationProvider')]
  public function testSharedConfirmationTable(string $entity_type): void {
    $node = Node::create(['type' => 'page', 'title' => 'Destination']);
    $node->save();
    LayoutBuilderEntityViewDisplay::load('node.page.default')->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', [
      'denylisted_blocks' => ['System' => ['system_powered_by_block']],
    ])->save();
    $destination = $node;
    if ($entity_type === 'page_template') {
      $destination = PageTemplate::create(['title' => 'Template destination', 'field_entity_reference' => $node]);
      $destination->save();
    }
    $storage = $this->container->get('plugin.manager.layout_builder.section_storage')->load('overrides', ['entity' => EntityContext::fromEntity($destination)]);
    $label = '<img src=x onerror=alert(1)> & news';
    $page_config = [
      0 => (new Section('layout_onecol'))->toArray(),
      2 => (new Section('layout_onecol', ['label' => $label], [
        new SectionComponent('allowed', 'content', ['id' => 'system_branding_block', 'label' => 'Allowed label']),
      ]))->toArray(),
      4 => (new Section('layout_onecol', ['label' => 'Restricted section'], [
        new SectionComponent('restricted', 'content', ['id' => 'system_powered_by_block']),
      ]))->toArray(),
    ];
    $tables = [];
    foreach ([ApplyPageTemplateForm::class, ImportPageConfigForm::class] as $form_class) {
      $form = $form_class::create($this->container);
      $tables[] = (new \ReflectionMethod($form, 'buildElementSelectionTable'))->invoke($form, $page_config, $destination, $storage);
    }

    $this->assertEquals($tables[0], $tables[1]);
    $table = $tables[0];
    $this->assertCount(3, $table['#header']);
    $this->assertTrue($table['#required']);
    $this->assertSame([0, '2--allowed', 1, '4--restricted'], array_keys($table['#options']));
    $this->assertSame(['2--allowed' => TRUE], $table['#default_value']);
    $this->assertSame('Allowed label', $table['#options']['2--allowed'][1]);
    $this->assertTrue($table['#options']['4--restricted']['#disabled']);
    $this->assertSame('This element is not allowed in this layout.', (string) $table['#options']['4--restricted'][2]);
    foreach ([0, 1] as $key) {
      $this->assertTrue($table['#options'][$key]['#disabled']);
      $this->assertSame(3, $table['#options'][$key][0]['colspan']);
    }
    $heading = $table['#options'][0][0]['data'];
    $this->assertSame('<h2>' . Html::escape($label) . '</h2>', trim((string) $this->container->get('renderer')->renderInIsolation($heading)));
  }

  /**
   * Provides both supported entity contexts for confirmation tables.
   */
  public static function confirmationDestinationProvider(): array {
    return [
      'node destination' => ['node'],
      'template destination' => ['page_template'],
    ];
  }

  /**
   * Creates an import containing one ordinary System component.
   */
  private function createPageConfig(): array {
    $component = new SectionComponent('component', 'content', ['id' => 'system_powered_by_block']);
    return [(new Section('layout_onecol', [], [$component]))->toArray()];
  }

}
