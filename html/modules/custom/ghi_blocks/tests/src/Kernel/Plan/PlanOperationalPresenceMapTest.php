<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\LazyMapBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanOperationalPresenceMap;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan operational presence map block plugin.
 *
 * @group ghi_blocks
 */
class PlanOperationalPresenceMapTest extends PlanBlockKernelTestBase {

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
