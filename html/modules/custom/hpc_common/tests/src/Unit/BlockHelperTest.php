<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\Router;
use Drupal\layout_builder\Section;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_common\Plugin\HPCBlockBase;
use Drupal\hpc_downloads\Interfaces\HPCDownloadContainerInterface;

/**
 * @covers Drupal\hpc_common\Helpers\BlockHelper
 */
class BlockHelperTest extends UnitTestCase {

  /**
   * Tests that editor lookups retain maps and contexts from nested containers.
   */
  public function testGetNestedBlockInstanceFromUnsavedLayout(): void {
    $uri = '/layout-builder-ipe/page_manager/edit/page_manager/homepage';
    $block = $this->createMock(HPCBlockBase::class);
    $block->method('getContextDefinitions')->willReturn([]);
    $block->expects($this->once())->method('injectFieldContexts');
    $block->expects($this->once())->method('setCurrentUri')->with($uri);
    $container_plugin = $this->createMock(HPCDownloadContainerInterface::class);
    $container_plugin->expects($this->once())->method('findContainedPlugin')->with('global_plan_overview_map', 'nested-map')->willReturn($block);
    $component = $this->createMock(SectionComponent::class);
    $component->method('getPluginId')->willReturn('global_homepages');
    $component->method('getUuid')->willReturn('container');
    $component->method('getPlugin')->willReturn($container_plugin);
    $storage = $this->createMock(SectionStorageInterface::class);
    $storage->method('getSections')->willReturn([new Section('layout_onecol', [], [$component])]);
    $storage->method('getContexts')->willReturn([]);
    $router = $this->createMock(Router::class);
    $router->method('match')->with($uri)->willReturn(['section_storage' => $storage]);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkRequest')->willReturn(TRUE);
    \Drupal::getContainer()->set('router.no_access_checks', $router);
    \Drupal::getContainer()->set('access_manager', $access_manager);

    $this->assertSame($block, BlockHelper::getBlockInstance($uri, 'global_plan_overview_map', 'nested-map'));
  }

  /**
   * Tests that editor routes resolve unsaved components and their page context.
   */
  public function testGetBlockInstanceFromUnsavedLayout(): void {
    $uri = '/node/17152/layout';
    $uuid = 'unsaved-map';
    $configuration = ['id' => 'plan_composite_map', 'label' => 'Unsaved map'];
    $block = $this->createMock(HPCBlockBase::class);
    $block->method('getConfiguration')->willReturn($configuration);
    $block->method('getContextDefinitions')->willReturn(['node' => new EntityContextDefinition('node')]);
    $node = $this->createMock(NodeInterface::class);
    $block->expects($this->once())->method('setContextValue')->with('node', $node);
    $block->expects($this->once())->method('injectFieldContexts');
    $block->expects($this->once())->method('setCurrentUri')->with($uri);

    $component = $this->createMock(SectionComponent::class);
    $component->method('getPluginId')->willReturn('plan_composite_map');
    $component->method('getUuid')->willReturn($uuid);
    $component->method('getPlugin')->willReturn($block);
    $section_storage = $this->createMock(SectionStorageInterface::class);
    $section_storage->method('getSections')->willReturn([new Section('layout_onecol', [], [$component])]);
    $section_storage->method('getContexts')->willReturn(['entity' => $this->createMock(EntityContext::class)]);
    $section_storage->method('getContextValue')->with('entity')->willReturn($node);
    $router = $this->createMock(Router::class);
    $router->method('match')->with($uri)->willReturn(['section_storage' => $section_storage]);
    $manager = $this->createMock(BlockManager::class);
    $manager->expects($this->once())->method('createInstance')->with('plan_composite_map', $configuration + ['uuid' => $uuid])->willReturn($block);
    \Drupal::getContainer()->set('router.no_access_checks', $router);
    \Drupal::getContainer()->set('plugin.manager.block', $manager);
    $access_manager = $this->createMock(AccessManagerInterface::class);
    $access_manager->method('checkRequest')->willReturnOnConsecutiveCalls(TRUE, TRUE, FALSE);
    \Drupal::getContainer()->set('access_manager', $access_manager);

    $this->assertSame($block, BlockHelper::getBlockInstance($uri, 'plan_composite_map', $uuid));
    $this->assertNull(BlockHelper::getBlockInstance($uri, 'plan_composite_map', 'missing-map'));
    $this->assertNull(BlockHelper::getBlockInstance($uri, 'plan_composite_map', $uuid));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock block manager.
    $block_manager = $this->prophesize(BlockManager::class);

    // Mock getDefinition method.
    $block_manager->getDefinition('plan_snapshot')->willReturn([
      'plugin_id' => 'plan_snapshot',
      'title' => 'Nigeria 2018',
    ]);

    // Set container.
    $container = new ContainerBuilder();
    $container->set('plugin.manager.block', $block_manager->reveal());
    \Drupal::setContainer($container);
  }

  /**
   * Test getting the storage id.
   *
   * @group BlockHelper
   */
  public function testGetStorageId() {
    // Mock HPCBlockBase.
    $hpc_block = $this->prophesize(HPCBlockBase::class);

    $hpc_block->getPluginId()->willReturn('country_snapshot');
    $hpc_block->getUuid()->willReturn('Med485-UIsdc98');

    $this->assertEquals('country_snapshot:Med485-UIsdc98', BlockHelper::getStorageId($hpc_block->reveal()));
  }

  /**
   * Test getting the plugin uuid from a storage id.
   *
   * @group BlockHelper
   */
  public function testGetPluginUuidFromStorageId() {
    $this->assertEquals('fedced4844-ref484', BlockHelper::getPluginUuidFromStorageId('plan_top_donors:fedced4844-ref484'));
  }

  /**
   * Test getting the plugin id from a storage id.
   *
   * @group BlockHelper
   */
  public function testGetPluginIdFromStorageId() {
    $this->assertEquals('plan_top_donors', BlockHelper::getPluginIdFromStorageId('plan_top_donors:fedced4844-ref484'));
  }

  /**
   * Test egtting the plugin definition from a storage id.
   *
   * @group BlockHelper
   */
  public function testGetPluginDefinitionFromStorageId() {
    $definition = [
      'plugin_id' => 'plan_snapshot',
      'title' => 'Nigeria 2018',
    ];

    $this->assertEquals($definition, BlockHelper::getPluginDefinitionFromStorageId('plan_snapshot:fedced4844-ref484'));
  }

}
