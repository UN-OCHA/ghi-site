<?php

namespace Drupal\Tests\ghi_subpages_custom\Kernel;

use Drupal\ghi_sections\Menu\SectionMenuItemInterface;
use Drupal\ghi_subpages_custom\Plugin\SectionMenuItem\CustomSubpage as SectionMenuItemCustomSubpage;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\Tests\ghi_sections\Kernel\SectionMenuTestBase;
use Drupal\Tests\ghi_subpages_custom\Traits\CustomSubpageTestTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Tests the custom subpage manager.
 *
 * @group ghi_subpages_custom
 */
class CustomSubpageManagerTest extends SectionMenuTestBase {

  use UserCreationTrait;
  use EntityReferenceFieldCreationTrait;
  use CustomSubpageTestTrait;

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
    'hpc_common',
    'ghi_base_objects',
    'ghi_subpages_custom',
  ];

  const CUSTOM_SUBPAGE_BUNDLE = 'custom_subpage';

  /**
   * The custom subpage manager to test.
   *
   * @var \Drupal\ghi_subpages_custom\CustomSubpageManager
   */
  protected $customSubpageManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => self::CUSTOM_SUBPAGE_BUNDLE])->save();
    $this->createEntityReferenceField('node', self::CUSTOM_SUBPAGE_BUNDLE, 'field_entity_reference', 'Section', 'node', 'default', [
      'target_bundles' => [self::SECTION_BUNDLE],
    ]);

    $this->customSubpageManager = \Drupal::service('ghi_subpages_custom.manager');
  }

  /**
   * Tests that nodes for a section can be loaded.
   */
  public function testLoadNodesForSection() {

    // Create a section.
    $section = $this->createSection();

    // Create custom subpages.
    $custom_subpage_1 = $this->createCustomSubpage($section);
    $custom_subpage_2 = $this->createCustomSubpage($section);

    $this->assertEquals([$custom_subpage_1->id(), $custom_subpage_2->id()], array_keys($this->customSubpageManager->loadNodesForSection($section)));

  }

  /**
   * Tests section menu items for custom subpages.
   */
  public function testCustomSubpageMenuItem() {

    // Create a section.
    $section = $this->createSection();
    $this->sectionMenuStorage->setSection($section);
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems()->getAll();
    $this->assertCount(0, $menu_items);

    /** @var \Drupal\ghi_subpages_custom\Plugin\SectionMenuItem\CustomSubpage $menu_plugin */
    $menu_plugin = $this->sectionMenuPluginManager->createInstance('custom_subpage');
    $menu_plugin->setSection($section);
    $menu_item = $menu_plugin->getItem();
    $this->assertNull($menu_item);

    // Create custom subpages.
    $custom_subpage_1 = $this->createCustomSubpage($section);
    $custom_subpage_2 = $this->createCustomSubpage($section);

    // Confirm we have menu items for them.
    $menu_item_1 = $custom_subpage_1->getSectionMenuItem();
    $menu_item_2 = $custom_subpage_2->getSectionMenuItem();
    $this->assertInstanceOf(SectionMenuItemInterface::class, $menu_item_1);
    $this->assertInstanceOf(SectionMenuItemInterface::class, $menu_item_2);
    $this->assertInstanceOf(SectionMenuItemCustomSubpage::class, $menu_item_1->getPlugin());
    $this->assertInstanceOf(SectionMenuItemCustomSubpage::class, $menu_item_2->getPlugin());

    // Confirm the section storage now lists 2 items.
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems()->getAll();
    $this->assertCount(2, $menu_items);

    // Delete a custom subpage and confirm that the menu item also get's
    // deleted.
    $custom_subpage_2->delete();
    $menu_items = $this->sectionMenuStorage->getSectionMenuItems()->getAll();
    $this->assertCount(1, $menu_items);
  }

}
