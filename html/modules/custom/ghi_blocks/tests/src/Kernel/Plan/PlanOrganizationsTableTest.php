<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanOrganizationsTable;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan organizations table block plugin.
 *
 * @group ghi_blocks
 */
class PlanOrganizationsTableTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanOrganizationsTable::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_organizations_table', $definition['id']);
    $this->assertEquals('Organizations Table', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Organizations overview', $metadata->defaultTitle);
    $this->assertArrayHasKey('project_search', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(ConfigurableTableBlockInterface::class, $plugin);
    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
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
    $this->assertArrayHasKey('plan_cluster', $definition['context_definitions']);
    $this->assertFalse($definition['context_definitions']['plan_cluster']->isRequired());
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
   * Tests the getTitleSubform method.
   */
  public function testGetTitleSubform() {
    $plugin = $this->getBlockPlugin();
    $title_subform = $plugin->getTitleSubform();
    $this->assertEquals('display', $title_subform);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanOrganizationsTable
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'organizations' => [
        'organization_ids' => NULL,
      ],
      'table' => [
        'columns' => [],
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_organizations_table', $configuration, $contexts);
  }

}
