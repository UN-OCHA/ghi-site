<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCompositeMap;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan composite map block plugin.
 *
 * @group ghi_blocks
 */
class PlanCompositeMapTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation(): void {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanCompositeMap::class, $plugin);
  }

  /**
   * Tests that configuration preview removes modal contents from map data.
   */
  public function testConfigurationPreviewMapStripsModalContents(): void {
    $plugin = $this->getBlockPlugin();
    $map = [
      'json' => [
        [
          'label' => 'Map tab',
          'locations' => [
            [
              'object_id' => 1,
              'name' => 'Location 1',
              'modal_contents' => [
                52191 => [
                  'polygon' => '<p>Polygon modal</p>',
                ],
              ],
            ],
          ],
        ],
      ],
      'id' => 'test-map',
      'settings_key' => 'plan_composite_map',
    ];

    $preview_map = $this->callPrivateMethod($plugin, 'getConfigurationPreviewMap', [$map]);

    $this->assertArrayHasKey('json', $preview_map);
    $this->assertArrayHasKey('modal_data_url', $preview_map);
    $this->assertArrayNotHasKey('modal_contents', $preview_map['json'][0]['locations'][0]);
    $this->assertSame('Location 1', $preview_map['json'][0]['locations'][0]['name']);
    $this->assertSame('test-map', $preview_map['id']);
    $this->assertSame('plan_composite_map', $preview_map['settings_key']);

    $token = basename(parse_url($preview_map['modal_data_url'], PHP_URL_PATH));
    $store = $this->container->get('keyvalue.expirable')
      ->get(MapModalContent::CONFIGURATION_PREVIEW_COLLECTION);
    $entry = $store->get(MapModalContent::buildStoreKey($token, '0', MapModalContent::DEFAULT_VARIANT_ID));
    $this->assertSame([
      '1' => [
        52191 => [
          'polygon' => '<p>Polygon modal</p>',
        ],
      ],
    ], $entry['modal_contents']);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanCompositeMap
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $contexts = $this->getPlanSectionContexts();
    return $this->createBlockPlugin('plan_composite_map', [], $contexts);
  }

}
