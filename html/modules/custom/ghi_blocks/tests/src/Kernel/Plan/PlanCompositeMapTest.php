<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Form\FormState;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ghi_blocks\Controller\BlockPreviewController;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanCompositeMap;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Symfony\Component\HttpFoundation\Request;

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
   * Tests that editor maps resolve unsaved storage rather than the public page.
   */
  public function testLayoutPreviewMapUri(): void {
    $plugin = $this->getBlockPlugin();
    $plugin->setCurrentUri('/plan/1266');
    $this->assertSame('/plan/1266', $this->callPrivateMethod($plugin, 'getMapPageUri'));

    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('section_storage')->willReturn($this->createMock(SectionStorageInterface::class));
    $this->setPrivateProperty($plugin, 'routeMatch', $route_match);
    $editor_uri = '/layout_builder/add/block/overrides/node.17152/0/content/plan_composite_map';
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create($editor_uri, 'POST', ['currentPath' => '/plan/1266']));
    try {
      $map_page_uri = $this->callPrivateMethod($plugin, 'getMapPageUri');
    }
    finally {
      $request_stack->pop();
    }
    $this->assertSame($editor_uri, $map_page_uri);
  }

  /**
   * Tests that the legend option defaults on and reflects saved configuration.
   */
  public function testCompactPolygonLegendConfiguration(): void {
    $plugin = $this->getBlockPlugin();
    $form_state = (new FormState())
      ->set('block', $plugin)
      ->set('current_subform', 'common');
    $form = $plugin->commonForm([], $form_state);
    $this->assertTrue($form['compact_polygon_legend']['#default_value']);

    foreach ([TRUE, FALSE] as $compact) {
      $configuration = $plugin->getConfiguration();
      $configuration['hpc']['common']['compact_polygon_legend'] = $compact;
      $plugin->setConfiguration($configuration);
      $form = $plugin->commonForm([], $form_state);
      $this->assertSame($compact, $form['compact_polygon_legend']['#default_value']);
    }
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

    $canvas_map = $this->callPrivateMethod($plugin, 'preparePreviewMap', [$map, 'test-map']);
    $this->assertArrayNotHasKey('modal_data_url', $canvas_map);
    $this->assertArrayNotHasKey('modal_contents', $canvas_map['json'][0]['locations'][0]);

    $configuration = $plugin->getConfiguration();
    $configuration['is_preview'] = TRUE;
    $plugin->setConfiguration($configuration);
    $preview_map = $this->callPrivateMethod($plugin, 'preparePreviewMap', [$map, 'test-map']);

    $this->assertArrayHasKey('json', $preview_map);
    $this->assertArrayHasKey('modal_data_url', $preview_map);
    $this->assertArrayNotHasKey('modal_contents', $preview_map['json'][0]['locations'][0]);
    $this->assertSame('Location 1', $preview_map['json'][0]['locations'][0]['name']);
    $this->assertSame('test-map', $preview_map['id']);
    $this->assertSame('plan_composite_map', $preview_map['settings_key']);

    $token = $this->callPrivateMethod($plugin, 'getPreviewStateToken');
    $manager = $this->container->get('ghi_blocks.preview_state_manager');
    $resource_key = MapModalContent::buildResourceKey('test-map', '0', MapModalContent::DEFAULT_VARIANT_ID);
    $modal_contents = $manager->getResource($token, MapModalContent::RESOURCE_NAMESPACE, $resource_key);
    $this->assertSame([
      '1' => [
        52191 => [
          'polygon' => '<p>Polygon modal</p>',
        ],
      ],
    ], $modal_contents);

    // Composite datasets start at zero, which is a valid modal resource key.
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create($preview_map['modal_data_url'], 'GET', [
      'map_id' => 'test-map',
      'data_index' => '0',
      'object_id' => '1',
    ]));
    try {
      $response = BlockPreviewController::create($this->container)->mapModalData($token);
    }
    finally {
      $request_stack->pop();
    }
    $this->assertSame($modal_contents['1'], json_decode($response->getContent(), TRUE));
    $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
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
