<?php

namespace Drupal\Tests\ghi_menu\Unit;

use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\ghi_menu\Plugin\Block\MegaMenuBlock;
use Drupal\system\MenuInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mega menu block.
 */
#[CoversClass(MegaMenuBlock::class)]
#[Group('ghi_menu')]
class MegaMenuBlockTest extends UnitTestCase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $cache_contexts_manager = $this->createMock(CacheContextsManager::class);
    $cache_contexts_manager->method('assertValidTokens')->willReturn(TRUE);
    $container = new ContainerBuilder();
    $container->set('cache_contexts_manager', $cache_contexts_manager);
    \Drupal::setContainer($container);
  }

  /**
   * Tests that disabled links are excluded when the menu tree is loaded.
   */
  public function testGetMenuItemsLoadsOnlyEnabledLinks(): void {
    $menu = $this->createMock(MenuInterface::class);
    $menu->method('id')->willReturn('gho-menu');

    $menu_storage = $this->createMock(EntityStorageInterface::class);
    $menu_storage->expects($this->once())
      ->method('load')
      ->with('gho-menu')
      ->willReturn($menu);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->with('menu')
      ->willReturn($menu_storage);

    $parameters = new MenuTreeParameters();
    $menu_tree = $this->createMock(MenuLinkTreeInterface::class);
    $menu_tree->expects($this->once())
      ->method('getCurrentRouteMenuTreeParameters')
      ->with('main')
      ->willReturn($parameters);
    $menu_tree->expects($this->once())
      ->method('load')
      ->with('gho-menu', $this->callback(function (MenuTreeParameters $parameters): bool {
        return $parameters->expandedParents === [] && $parameters->conditions['enabled'] === 1;
      }))
      ->willReturn([]);
    $menu_tree->expects($this->once())
      ->method('transform')
      ->with([], [
        ['callable' => 'menu.default_tree_manipulators:checkAccess'],
        ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
      ])
      ->willReturn([]);

    $block = new TestableMegaMenuBlock([
      'menu' => 'gho-menu',
      'label_display' => FALSE,
    ], 'mega_menu_block', ['provider' => 'ghi_menu']);
    $block->setEntityTypeManager($entity_type_manager);
    $block->setMenuTree($menu_tree);

    $this->assertSame([], $block->getMenuItems());
  }

  /**
   * Tests that top-level items without visible children are not rendered.
   */
  public function testBuildSkipsTopLevelItemsWithoutVisibleChildren(): void {
    $empty_link = $this->createMock(MenuLinkInterface::class);
    $empty_item = new MenuLinkTreeElement($empty_link, FALSE, 1, FALSE, []);

    $hidden_child = new MenuLinkTreeElement($this->createMock(MenuLinkInterface::class), FALSE, 2, FALSE, []);
    $hidden_subtree = ['hidden_child' => $hidden_child];
    $hidden_item = new MenuLinkTreeElement($this->createMock(MenuLinkInterface::class), TRUE, 1, FALSE, $hidden_subtree);

    $child_item = new MenuLinkTreeElement($this->createMock(MenuLinkInterface::class), FALSE, 2, FALSE, []);
    $visible_link = $this->createMock(MenuLinkInterface::class);
    $visible_link->method('getTitle')->willReturn('Visible tab');
    $visible_link->method('getWeight')->willReturn(0);
    $visible_subtree = ['child' => $child_item];
    $visible_item = new MenuLinkTreeElement($visible_link, TRUE, 1, FALSE, $visible_subtree);

    $menu_tree = $this->createMock(MenuLinkTreeInterface::class);
    $menu_tree->expects($this->exactly(2))
      ->method('build')
      ->willReturnCallback(function (array $subtree) use ($hidden_subtree, $visible_subtree): array {
        $items = $subtree === $visible_subtree ? [
          ['attributes' => [], 'below' => []],
        ] : [];
        $this->assertContains($subtree, [$hidden_subtree, $visible_subtree], TRUE);
        return [
          '#cache' => [
            'contexts' => [],
            'tags' => [],
          ],
          '#items' => $items,
        ];
      });

    $block = new FixedMenuItemsMegaMenuBlock([
      'menu' => 'gho-menu',
      'label_display' => FALSE,
    ], 'mega_menu_block', ['provider' => 'ghi_menu']);
    $block->setMenuTree($menu_tree);
    $block->setMenuItems([
      'empty' => $empty_item,
      'hidden' => $hidden_item,
      'visible' => $visible_item,
    ]);

    $build = $block->build();

    $this->assertArrayNotHasKey('empty', $build['tab_content']);
    $this->assertArrayNotHasKey('hidden', $build['tab_content']);
    $this->assertArrayHasKey('visible', $build['tab_content']);
    $this->assertSame('visible', $build['tabs']['tabs__active_tab']['#value']);
  }

}

/**
 * Exposes the mega menu block dependencies for unit tests.
 */
class TestableMegaMenuBlock extends MegaMenuBlock {

  /**
   * Sets the entity type manager.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager): void {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Sets the menu tree service.
   */
  public function setMenuTree(MenuLinkTreeInterface $menu_tree): void {
    $this->menuTree = $menu_tree;
  }

}

/**
 * Supplies fixed menu items to the block for unit tests.
 */
class FixedMenuItemsMegaMenuBlock extends TestableMegaMenuBlock {

  /**
   * The menu items returned by the block.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeElement[]
   */
  private array $menuItems = [];

  /**
   * Sets the menu items returned by the block.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement[] $menu_items
   *   The menu items.
   */
  public function setMenuItems(array $menu_items): void {
    $this->menuItems = $menu_items;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuItems() {
    return $this->menuItems;
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuCacheTags() {
    return [];
  }

}
