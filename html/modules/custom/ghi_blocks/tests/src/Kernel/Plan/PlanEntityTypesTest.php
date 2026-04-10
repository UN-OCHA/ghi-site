<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityTypes;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan entity types block plugin.
 *
 * @group ghi_blocks
 */
class PlanEntityTypesTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanEntityTypes::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_entity_types', $definition['id']);
    $this->assertEquals('Entity Types', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertArrayHasKey('entities', $metadata->dataSources);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('entity_ids', $default_config);
    $this->assertNull($default_config['entity_ids']);
    $this->assertArrayHasKey('entity_ref_code', $default_config);
    $this->assertNull($default_config['entity_ref_code']);
    $this->assertArrayHasKey('id_type', $default_config);
    $this->assertNull($default_config['id_type']);
    $this->assertArrayHasKey('sort', $default_config);
    $this->assertFalse($default_config['sort']);
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
   * Tests block contexts requirements.
   */
  public function testBlockContexts() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertArrayHasKey('context_definitions', $definition);
    $this->assertArrayHasKey('node', $definition['context_definitions']);
    $this->assertArrayHasKey('plan', $definition['context_definitions']);
  }

  /**
   * Tests the getConfigurationDefaults method returns expected keys.
   */
  public function testGetConfigurationDefaultsKeys() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertCount(5, $default_config);
    $this->assertArrayHasKey('entity_ids', $default_config);
    $this->assertArrayHasKey('entity_ref_code', $default_config);
    $this->assertArrayHasKey('id_type', $default_config);
    $this->assertArrayHasKey('sort', $default_config);
    $this->assertArrayHasKey('sort_column', $default_config);
  }

  /**
   * Tests the getAutomaticBlockTitle method.
   */
  public function testGetAutomaticBlockTitleWithEmptyEntities() {
    $plugin = $this->getBlockPlugin();

    $title = $plugin->getAutomaticBlockTitle();

    $this->assertNull($title);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @param array $additional_config
   *   Additional configuration to merge with defaults.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityTypes
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $additional_config = []) {
    $configuration = array_merge([
      'entity_ids' => NULL,
      'entity_ref_code' => NULL,
      'id_type' => NULL,
      'sort' => FALSE,
      'sort_column' => NULL,
    ], $additional_config);

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_entity_types', $configuration, $contexts);
  }

}
