<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityAttachmentsTable;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan entity attachments table block plugin.
 *
 * @group ghi_blocks
 */
class PlanEntityAttachmentsTableTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanEntityAttachmentsTable::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_entity_attachments_table', $definition['id']);
    $this->assertEquals('Entity Attachments Table', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Indicator overview', $metadata->defaultTitle);
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
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('attachments', $default_config);
    $this->assertArrayHasKey('entity_attachments', $default_config['attachments']);
    $this->assertArrayHasKey('entities', $default_config['attachments']['entity_attachments']);
    $this->assertArrayHasKey('entity_ids', $default_config['attachments']['entity_attachments']['entities']);
    $this->assertNull($default_config['attachments']['entity_attachments']['entities']['entity_ids']);

    $this->assertArrayHasKey('table', $default_config);
    $this->assertArrayHasKey('columns', $default_config['table']);
    $this->assertEmpty($default_config['table']['columns']);

    $this->assertArrayHasKey('display', $default_config);
    $this->assertArrayHasKey('table_type', $default_config['display']);
    $this->assertEquals(PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED, $default_config['display']['table_type']);
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
    $this->assertEquals('attachments', $default_subform);
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
   * Tests the table type constants.
   */
  public function testTableTypeConstants() {
    $this->assertEquals('grouped', PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED);
    $this->assertEquals('flat', PlanEntityAttachmentsTable::TABLE_TYPE_FLAT);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanEntityAttachmentsTable
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'attachments' => [
        'entity_attachments' => [
          'entities' => [
            'entity_ids' => NULL,
          ],
          'attachments' => [
            'entity_type' => NULL,
            'attachment_type' => NULL,
            'attachment_id' => NULL,
          ],
        ],
      ],
      'table' => [
        'columns' => [],
      ],
      'display' => [
        'table_type' => PlanEntityAttachmentsTable::TABLE_TYPE_GROUPED,
        'default_entity' => NULL,
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_entity_attachments_table', $configuration, $contexts);
  }

}
