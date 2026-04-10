<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;

/**
 * Tests the plan attachment map block plugin.
 *
 * @group ghi_blocks
 */
class PlanAttachmentMapTest extends PlanBlockKernelTestBase {

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanAttachmentMap::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('plan_attachment_map', $definition['id']);
    $this->assertEquals('Attachment Map', (string) $definition['admin_label']);
    $this->assertEquals('Plan elements', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertEquals('Data by location', $metadata->defaultTitle);
    $this->assertArrayHasKey('attachment', $metadata->dataSources);
    $this->assertArrayHasKey('country', $metadata->dataSources);
    $this->assertArrayHasKey('entities', $metadata->dataSources);
  }

  /**
   * Tests block interfaces implementation.
   */
  public function testBlockInterfaces() {
    $plugin = $this->getBlockPlugin();

    $this->assertInstanceOf(MultiStepFormBlockInterface::class, $plugin);
    $this->assertInstanceOf(OverrideDefaultTitleBlockInterface::class, $plugin);
    $this->assertInstanceOf(ConfigValidationInterface::class, $plugin);
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

    $this->assertArrayHasKey('map', $default_config);
    $this->assertArrayHasKey('common', $default_config['map']);
    $this->assertArrayHasKey('comment', $default_config['map']['common']);
    $this->assertNull($default_config['map']['common']['comment']);
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
   * Tests the style constant.
   */
  public function testStyleConstant() {
    $this->assertEquals('circle', PlanAttachmentMap::STYLE_CIRCLE);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\Plan\PlanAttachmentMap
   *   The block plugin instance.
   */
  private function getBlockPlugin() {
    $configuration = [
      'attachments' => [
        'attachment_id' => NULL,
      ],
      'map' => [
        'common' => [
          'comment' => NULL,
        ],
      ],
    ];

    $contexts = $this->getPlanSectionContexts();

    return $this->createBlockPlugin('plan_attachment_map', $configuration, $contexts);
  }

}
