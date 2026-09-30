<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Plan;

use Drupal\ghi_blocks\Interfaces\AttachmentTableInterface;
use Drupal\ghi_blocks\Interfaces\ConfigValidationInterface;
use Drupal\ghi_blocks\Interfaces\ConfigurableTableBlockInterface;
use Drupal\ghi_blocks\Interfaces\MultiStepFormBlockInterface;
use Drupal\ghi_blocks\Interfaces\OverrideDefaultTitleBlockInterface;
use Drupal\ghi_blocks\Plugin\Block\Plan\PlanGoverningEntitiesCaseloadsTable;
use Drupal\ghi_plans\ApiObjects\Prototypes\AttachmentPrototype;
use Drupal\ghi_plans\Plugin\FabricQuery\AttachmentPrototypeQuery;
use Drupal\ghi_subpages\SubpageManager;
use Drupal\hpc_api\Query\FabricQueryManager;
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
   * Tests that configuration repair converts legacy data point indexes.
   */
  public function testFixConfigErrorsConvertsLegacyDataPoints(): void {
    $original_prototype = $this->mockAttachmentPrototype(10, 'Old prototype', ['target', 'cumulative_reach']);
    $new_prototype = $this->mockAttachmentPrototype(20, 'New prototype', ['cumulative_reach', 'target']);
    $plugin = $this->mockRepairBlock($this->getLegacyRepairConfiguration(), $new_prototype, [
      10 => $original_prototype,
      20 => $new_prototype,
    ]);

    $plugin->fixConfigErrors();

    $config = $plugin->getBlockConfig();
    $this->assertSame(20, $config['base']['prototype_id']);
    $this->assertSame('cumulative_reach', $config['table']['columns'][0]['config']['data_point']['data_points'][0]['metric_type']);
    $this->assertArrayNotHasKey('index', $config['table']['columns'][0]['config']['data_point']['data_points'][0]);
    $this->assertSame('target', $config['table']['columns'][0]['config']['data_point']['data_points'][1]['metric_type']);
    $this->assertArrayNotHasKey('index', $config['table']['columns'][0]['config']['data_point']['data_points'][1]);
    $this->assertSame('cumulative_reach', $config['table']['columns'][1]['config']['data_point']);
    $this->assertSame('target', $config['table']['columns'][1]['config']['baseline']);
  }

  /**
   * Tests that incomplete prototype data does not break configuration repair.
   */
  public function testFixConfigErrorsHandlesMissingPrototype(): void {
    $new_prototype = $this->mockAttachmentPrototype(20, 'New prototype', ['cumulative_reach', 'target']);
    $plugin = $this->mockRepairBlock($this->getLegacyRepairConfiguration(), $new_prototype, [
      20 => $new_prototype,
    ]);

    $plugin->fixConfigErrors();

    $config = $plugin->getBlockConfig();
    $this->assertSame(10, $config['base']['prototype_id']);
    $this->assertSame(1, $config['table']['columns'][0]['config']['data_point']['data_points'][0]['index']);
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

  /**
   * Mock a block configured for repair in a new prototype context.
   */
  private function mockRepairBlock(array $configuration, AttachmentPrototype $new_prototype, array $loaded_prototypes): PlanGoverningEntitiesCaseloadsTable {
    $prototype_query = $this->prophesize(AttachmentPrototypeQuery::class);
    $prototype_query->getPrototypes([10, 20])->willReturn($loaded_prototypes);
    $fabric_query_manager = $this->prophesize(FabricQueryManager::class);
    $fabric_query_manager->createInstance('attachment_prototype')->willReturn($prototype_query->reveal());

    $plugin = $this->getMockBuilder(PlanGoverningEntitiesCaseloadsTable::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getUniquePrototypes'])
      ->getMock();
    $plugin->method('getUniquePrototypes')->willReturn([20 => $new_prototype]);

    $reflection = new \ReflectionClass($plugin);
    $configuration_property = $reflection->getParentClass()->getProperty('configuration');
    $configuration_property->setValue($plugin, ['hpc' => $configuration]);
    $fabric_query_manager_property = $reflection->getParentClass()->getParentClass()->getProperty('fabricQueryManager');
    $fabric_query_manager_property->setValue($plugin, $fabric_query_manager->reveal());
    return $plugin;
  }

  /**
   * Get block configuration containing a legacy data point index.
   */
  private function getLegacyRepairConfiguration(): array {
    return [
      'base' => ['prototype_id' => 10],
      'table' => [
        'columns' => [
          [
            'item_type' => 'data_point',
            'config' => [
              'data_point' => [
                'processing' => 'calculated',
                'data_points' => [
                  ['index' => 1],
                  ['index' => 0],
                ],
              ],
            ],
          ],
          [
            'item_type' => 'spark_line_chart',
            'config' => [
              'data_point' => 1,
              'show_baseline' => TRUE,
              'baseline' => 0,
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * Mock an attachment prototype used by block configuration repair.
   */
  private function mockAttachmentPrototype(int $id, string $name, array $field_types): AttachmentPrototype {
    $prototype = $this->createMock(AttachmentPrototype::class);
    $prototype->method('id')->willReturn($id);
    $prototype->method('getName')->willReturn($name);
    $prototype->method('getFieldTypes')->willReturn($field_types);
    $prototype->method('resolveMetricType')->willReturnCallback(function ($data_point) use ($field_types): ?string {
      if (is_int($data_point) || (is_string($data_point) && ctype_digit($data_point))) {
        return $field_types[(int) $data_point] ?? NULL;
      }
      return is_string($data_point) && in_array($data_point, $field_types, TRUE) ? $data_point : NULL;
    });
    return $prototype;
  }

}
