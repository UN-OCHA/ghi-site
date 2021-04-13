<?php

namespace Drupal\Tests\hpc_common\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\Core\Block\BlockManager;
use Drupal\Core\DependencyInjection\ContainerBuilder;

use Drupal\hpc_common\Helpers\BlockHelper;
use Drupal\hpc_common\Plugin\HPCBlockBase;

/**
 * @covers Drupal\hpc_common\Helpers\BlockHelper
 */
class BlockHelperTest extends UnitTestCase {

  /**
   * The block helper class.
   *
   * @var \Drupal\hpc_common\Helpers\BlockHelper
   */
  protected $blockHelper;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
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

    $this->blockHelper = new BlockHelper();
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown() {
    parent::tearDown();
    unset($this->blockHelper);

    $container = new ContainerBuilder();
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

    $this->assertEquals('country_snapshot:Med485-UIsdc98', $this->blockHelper->getStorageId($hpc_block->reveal()));
  }

  /**
   * Test getting the plugin uuid from a storage id.
   *
   * @group BlockHelper
   */
  public function testGetPluginUuidFromStorageId() {
    $this->assertEquals('fedced4844-ref484', $this->blockHelper->getPluginUuidFromStorageId('plan_top_donors:fedced4844-ref484'));
  }

  /**
   * Test getting the plugin id from a storage id.
   *
   * @group BlockHelper
   */
  public function testGetPluginIdFromStorageId() {
    $this->assertEquals('plan_top_donors', $this->blockHelper->getPluginIdFromStorageId('plan_top_donors:fedced4844-ref484'));
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

    $this->assertEquals($definition, $this->blockHelper->getPluginDefinitionFromStorageId('plan_snapshot:fedced4844-ref484'));
  }

}
