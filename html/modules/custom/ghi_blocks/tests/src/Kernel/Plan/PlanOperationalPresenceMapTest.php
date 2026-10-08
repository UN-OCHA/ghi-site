<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Map\MapModalContent;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanOperationalPresenceMap;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the plan operational presence map block plugin.
 */
#[Group('ghi_blocks')]
class PlanOperationalPresenceMapTest extends PlanBlockKernelTestBase {

  /**
   * Tests that the view switcher targets the editor's unsaved layout.
   */
  public function testViewSwitcherUsesEditorUri(): void {
    $plugin = $this->getBlockPlugin();
    $configuration = $plugin->getConfiguration();
    $configuration['hpc']['display']['available_views'] = ['organization', 'project'];
    $plugin->setConfiguration($configuration);
    $plugin->setCurrentUri('/plan/1266/presence');
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')->with('section_storage')->willReturn($this->createMock(SectionStorageInterface::class));
    $this->setPrivateProperty($plugin, 'routeMatch', $route_match);
    $editor_uri = '/layout_builder/add/block/overrides/node.1/0/content/plan_operational_presence_map';
    $request_stack = $this->container->get('request_stack');
    $request_stack->push(Request::create($editor_uri));
    try {
      $switcher = $this->callPrivateMethod($plugin, 'getViewSwitcher', ['organization']);
    }
    finally {
      $request_stack->pop();
    }

    $this->assertSame($editor_uri, $switcher['#uri']);
  }

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanOperationalPresenceMap::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_operational_presence_map', $definition['id']);
    $this->assertEquals('Operational Presence Map', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Operations by admin area', $metadata->defaultTitle);
    $this->assertArrayHasKey('locations', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(LazyMapBlockInterface::class, $plugin);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('organizations', $default_config);
    $this->assertArrayHasKey('organization_ids', $default_config['organizations']);
    $this->assertNull($default_config['organizations']['organization_ids']);

    $this->assertArrayHasKey('display', $default_config);
    $this->assertArrayHasKey('available_views', $default_config['display']);
    $this->assertEmpty($default_config['display']['available_views']);
    $this->assertArrayHasKey('default_view', $default_config['display']);
    $this->assertNull($default_config['display']['default_view']);
    $this->assertArrayHasKey('disclaimer', $default_config['display']);
    $this->assertNull($default_config['display']['disclaimer']);
    $this->assertArrayHasKey('pcodes_enabled', $default_config['display']);
    $this->assertFalse($default_config['display']['pcodes_enabled']);
  }

  /**
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('plan', $definition['context_definitions']);
    $this->assertArrayHasKey('plan_cluster', $definition['context_definitions']);
    $this->assertFalse($definition['context_definitions']['plan_cluster']->isRequired());
  }

  /**
   * Tests the default views constant.
   */
  public function testDefaultViewsConstant() {
    $expected = [
      'organization' => 'organization',
      'cluster' => 'cluster',
      'project' => 'project',
    ];
    $this->assertEquals($expected, PlanOperationalPresenceMap::DEFAULT_VIEWS);
  }

  /**
   * Tests the getDefaultSubform method.
   */
  public function testGetDefaultSubform() {
    $plugin = $this->getBlockPlugin();
    $default_subform = $plugin->getDefaultSubform();
    $this->assertEquals('organizations', $default_subform);
  }

  /**
   * Tests that configuration preview removes root modal contents from map data.
   */
  public function testPreviewMapStripsModalContents(): void {
    $plugin = $this->getBlockPlugin();
    $map = [
      'json' => [
        'locations' => [
          [
            'object_id' => 10,
            'name' => 'Location',
          ],
        ],
        'modal_contents' => [
          '10' => ['content' => '<p>Presence modal</p>'],
        ],
      ],
      'id' => 'test-map',
      'settings_key' => 'plan_operational_presence_map',
    ];

    $layout_builder_map = $this->callPrivateMethod($plugin, 'preparePreviewMap', [$map, 'test-map']);
    $this->assertArrayNotHasKey('modal_data_url', $layout_builder_map);
    $this->assertArrayNotHasKey('modal_contents', $layout_builder_map['json']);

    $configuration = $plugin->getConfiguration();
    $configuration['is_preview'] = TRUE;
    $plugin->setConfiguration($configuration);
    $preview_map = $this->callPrivateMethod($plugin, 'preparePreviewMap', [$map, 'test-map']);

    $this->assertArrayHasKey('modal_data_url', $preview_map);
    $this->assertArrayNotHasKey('modal_contents', $preview_map['json']);
    $this->assertSame('Location', $preview_map['json']['locations'][0]['name']);

    $token = $this->callPrivateMethod($plugin, 'getPreviewStateToken');
    $this->assertStringContainsString('/block-preview/' . $token . '/map-data/modal', $preview_map['modal_data_url']);
    $resource_key = MapModalContent::buildResourceKey('test-map', MapModalContent::DEFAULT_DATA_INDEX, MapModalContent::DEFAULT_VARIANT_ID);
    $modal_contents = $this->container->get('ghi_blocks.preview_state_manager')->getResource($token, MapModalContent::RESOURCE_NAMESPACE, $resource_key);
    $this->assertSame(['10' => ['content' => '<p>Presence modal</p>']], $modal_contents);
  }

  /**
   * Tests map data URL selection for saved and preview block state.
   */
  public function testMapDataUrlUsesCurrentBlockState(): void {
    $plugin = $this->getBlockPlugin();
    $configuration = $plugin->getConfiguration();
    $configuration['uuid'] = 'test-block-uuid';
    $plugin->setConfiguration($configuration);
    $data_url = $this->callPrivateMethod($plugin, 'getMapDataUrl', ['test-map', ['view' => 'organization']]);
    $this->assertStringContainsString('/map-data/plan_operational_presence_map/', $data_url);
    $this->assertStringContainsString('view=organization', $data_url);

    $configuration = $plugin->getConfiguration();
    $configuration['is_preview'] = TRUE;
    $plugin->setConfiguration($configuration);
    $this->assertNull($this->callPrivateMethod($plugin, 'getMapDataUrl', ['test-map']));
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanOperationalPresenceMap
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'organizations' => [
        'organization_ids' => NULL,
      ],
      'display' => [
        'available_views' => [],
        'default_view' => NULL,
        'disclaimer' => NULL,
        'pcodes_enabled' => FALSE,
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_operational_presence_map', $configuration, $contexts);
  }

}
