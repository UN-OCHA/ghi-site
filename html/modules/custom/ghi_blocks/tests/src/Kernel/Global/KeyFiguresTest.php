<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\KeyFigures;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the Key Figures block plugin.
 *
 * @group ghi_blocks
 */
class KeyFiguresTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(KeyFigures::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('global_key_figures', $definition['id']);
    $this->assertEquals('Key figures', (string) $definition['admin_label']);
    $this->assertEquals('Global', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertIsArray($metadata->dataSources);

    $data_sources = $metadata->dataSources;
    $this->assertArrayHasKey('plans_overview', $data_sources);
    $this->assertArrayHasKey('funding_overview', $data_sources);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('key_figures', $default_config);
    $this->assertArrayHasKey('items', $default_config['key_figures']);
    $this->assertEmpty($default_config['key_figures']['items']);
  }

  /**
   * Tests the block build with empty configuration.
   */
  public function testBlockBuildWithEmptyConfiguration() {
    $plugin = $this->getBlockPlugin();

    $build = $plugin->buildContent();

    $this->assertNull($build);
  }

  /**
   * Tests the shouldDisplayTitle method.
   */
  public function testShouldDisplayTitle() {
    $plugin = $this->getBlockPlugin();

    $result = $plugin->shouldDisplayTitle();

    $this->assertFalse($result);
  }

  /**
   * Tests the getDefaultSubform method.
   */
  public function testGetDefaultSubform() {
    $plugin = $this->getBlockPlugin();

    $result = $plugin->getDefaultSubform();

    $this->assertEquals('key_figures', $result);
  }

  /**
   * Tests the getAllowedItemTypes method.
   */
  public function testGetAllowedItemTypes() {
    $plugin = $this->getBlockPlugin();

    $item_types = $plugin->getAllowedItemTypes();

    $this->assertIsArray($item_types);
    $this->assertArrayHasKey('item_group', $item_types);
    $this->assertArrayHasKey('plan_overview_data', $item_types);
    $this->assertArrayHasKey('label_value', $item_types);

    $plan_overview_data = $item_types['plan_overview_data']['item_types'];
    $this->assertArrayHasKey('people_in_need', $plan_overview_data);
    $this->assertArrayHasKey('people_target', $plan_overview_data);
    $this->assertArrayHasKey('people_reached', $plan_overview_data);
    $this->assertArrayHasKey('total_funding', $plan_overview_data);
    $this->assertArrayHasKey('total_requirements', $plan_overview_data);
    $this->assertArrayHasKey('funding_progress', $plan_overview_data);
  }

  /**
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('year', $definition['context_definitions']);
  }

  /**
   * Tests cache tags generation.
   */
  public function testCacheTags() {
    $plugin = $this->getBlockPlugin();
    $configuration = $plugin->getConfiguration();
    $configuration['uuid'] = 'block_uuid';
    $plugin->setConfiguration($configuration);

    $cache_tags = $plugin->getCacheTags();
    $this->assertIsArray($cache_tags);
    $this->assertContains('global_key_figures:block_uuid', $cache_tags);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @param array $additional_config
   *   Additional configuration to merge with defaults.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\GlobalPage\KeyFigures
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $additional_config = []) {
    $configuration = array_merge([
      'key_figures' => [
        'items' => [],
      ],
    ], $additional_config);

    $contexts = [
      'year' => new Context(new ContextDefinition('integer'), 2024),
    ];

    return $this->createBlockPlugin('global_key_figures', $configuration, $contexts);
  }

}
