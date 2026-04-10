<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesCaseloadsTable;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan governing entities caseloads table block plugin.
 *
 * @group ghi_blocks
 */
class PlanGoverningEntitiesCaseloadsTableTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanGoverningEntitiesCaseloadsTable::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_governing_entities_caseloads_table', $definition['id']);
    $this->assertEquals('Governing Entities Caseloads Table', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Cluster caseloads', $metadata->defaultTitle);
    $this->assertArrayHasKey('entities', $metadata->dataSources);
    $this->assertArrayHasKey('attachment', $metadata->dataSources);
    $this->assertArrayHasKey('attachment_prototype', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(AttachmentTableInterface::class, $plugin);
    $this->assertInstanceOf(ConfigValidationInterface::class, $plugin);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('base', $default_config);
    $this->assertArrayHasKey('include_non_caseloads', $default_config['base']);
    $this->assertFalse($default_config['base']['include_non_caseloads']);
    $this->assertArrayHasKey('include_unpublished_clusters', $default_config['base']);
    $this->assertFalse($default_config['base']['include_unpublished_clusters']);
    $this->assertArrayHasKey('prototype_id', $default_config['base']);
    $this->assertNull($default_config['base']['prototype_id']);

    $this->assertArrayHasKey('table', $default_config);
    $this->assertArrayHasKey('columns', $default_config['table']);
    $this->assertEmpty($default_config['table']['columns']);
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
   * Tests the getDefaultSubform method.
   */
  public function testGetDefaultSubform() {
    $plugin = $this->getBlockPlugin();
    $default_subform = $plugin->getDefaultSubform();
    $this->assertEquals('table', $default_subform);
  }

  /**
   * Tests the getTitleSubform method.
   */
  public function testGetTitleSubform() {
    $plugin = $this->getBlockPlugin();
    $title_subform = $plugin->getTitleSubform();
    $this->assertEquals('base', $title_subform);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesCaseloadsTable
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'base' => [
        'include_non_caseloads' => FALSE,
        'include_unpublished_clusters' => FALSE,
        'prototype_id' => NULL,
      ],
      'table' => [
        'columns' => [],
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    $subpage_manager = $this->prophesize(SubpageManager::class);
    $subpage_manager->loadSubpageForBaseObject()->willReturn(NULL);

    $container = \Drupal::getContainer();
    $container->set('ghi_subpages.manager', $subpage_manager->reveal());

    return $this->createBlockPlugin('plan_governing_entities_caseloads_table', $configuration, $contexts);
  }

}
