<?php

namespace Drupal\Tests\ghi_blocks\Kernel\Global;

use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanTable;
use Drupal\Tests\ghi_blocks\Kernel\PlanBlockKernelTestBase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Tests the Plan Table block plugin.
 *
 * @group ghi_blocks
 */
class PlanTableTest extends PlanBlockKernelTestBase {

  use ProphecyTrait;

  /**
   * Tests the block plugin instantiation.
   */
  public function testBlockPluginInstantiation() {
    $plugin = $this->getBlockPlugin();
    $this->assertInstanceOf(PlanTable::class, $plugin);
  }

  /**
   * Tests block plugin annotation and metadata.
   */
  public function testBlockPluginAnnotation() {
    $plugin = $this->getBlockPlugin();
    $definition = $plugin->getPluginDefinition();

    $this->assertEquals('global_plan_table', $definition['id']);
    $this->assertEquals('Plan table', (string) $definition['admin_label']);
    $this->assertEquals('Global', (string) $definition['category']);

    $metadata = $plugin->metadata();
    $this->assertIsArray($metadata->dataSources);

    $data_sources = $metadata->dataSources;
    $this->assertArrayHasKey('plans_overview', $data_sources);
  }

  /**
   * Tests the default block configuration.
   */
  public function testDefaultConfiguration() {
    $plugin = $this->getBlockPlugin();
    $default_config = $this->callPrivateMethod($plugin, 'getConfigurationDefaults');

    $this->assertArrayHasKey('plans', $default_config);
    $this->assertArrayHasKey('hide_unpublished', $default_config['plans']);
    $this->assertFalse($default_config['plans']['hide_unpublished']);
    $this->assertArrayHasKey('hide_empty_requirements', $default_config['plans']);
    $this->assertFalse($default_config['plans']['hide_empty_requirements']);
    $this->assertArrayHasKey('table', $default_config);
    $this->assertArrayHasKey('top_note', $default_config['table']);
    $this->assertArrayHasKey('fts_icon', $default_config['table']);
    $this->assertTrue($default_config['table']['fts_icon']);
  }

  /**
   * Tests the block build with empty plans.
   */
  public function testBlockBuildWithEmptyPlans() {
    $plugin = $this->getBlockPlugin();
    $this->mockEmptyPlanOverviewQuery($plugin);

    $build = $plugin->buildContent();

    $this->assertNull($build);
  }

  /**
   * Tests the block build returns an array structure.
   */
  public function testBlockBuildReturnsArray() {
    $plugin = $this->getBlockPlugin();

    $plans = [
      $this->mockPlan()->reveal(),
    ];
    $this->mockPlanOverviewQuery($plugin, $plans);

    $build = $plugin->buildContent();

    $this->assertIsArray($build);
    $this->assertArrayHasKey('#cache', $build);
  }

  /**
   * Tests the buildTableData method with empty plans.
   */
  public function testBuildTableDataWithEmptyPlans() {
    $plugin = $this->getBlockPlugin();
    $this->mockEmptyPlanOverviewQuery($plugin);

    $table_data = $plugin->buildTableData();

    $this->assertNull($table_data);
  }

  /**
   * Tests the buildTableData method returns an array structure with plans.
   */
  public function testBuildTableDataReturnsArray() {
    $plugin = $this->getBlockPlugin();

    $plans = [
      $this->mockPlan()->reveal(),
    ];
    $this->mockPlanOverviewQuery($plugin, $plans);

    $table_data = $plugin->buildTableData();

    $this->assertIsArray($table_data);
    $this->assertArrayHasKey('header', $table_data);
    $this->assertArrayHasKey('rows', $table_data);
    $this->assertArrayHasKey('cache_tags', $table_data);
  }

  /**
   * Tests the buildDownloadData method returns an array.
   */
  public function testBuildDownloadDataReturnsArray() {
    $plugin = $this->getBlockPlugin();

    $plans = [
      $this->mockPlan()->reveal(),
    ];
    $this->mockPlanOverviewQuery($plugin, $plans);

    $download_data = $plugin->buildDownloadData();

    $this->assertIsArray($download_data);
    $this->assertArrayHasKey('header', $download_data);
    $this->assertArrayHasKey('rows', $download_data);
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
    $this->assertContains('global_plan_table:block_uuid', $cache_tags);
  }

  /**
   * Get a block plugin with default configuration.
   *
   * @param array $additional_config
   *   Additional configuration to merge with defaults.
   *
   * @return \Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanTable
   *   The block plugin instance.
   */
  private function getBlockPlugin(array $additional_config = []) {
    $configuration = array_merge([
      'plans' => [
        'hide_unpublished' => FALSE,
        'hide_empty_requirements' => FALSE,
      ],
      'table' => [
        'top_note' => NULL,
        'fts_icon' => TRUE,
        'comment' => NULL,
      ],
    ], $additional_config);

    $contexts = [
      'year' => new Context(new ContextDefinition('integer'), 2024),
    ];

    return $this->createBlockPlugin('global_plan_table', $configuration, $contexts);
  }

  /**
   * Creates a mock plan for testing purposes.
   *
   * @return \Prophecy\Prophecy\ObjectProphecy
   *   The mocked plan object.
   */
  private function mockPlan() {
    static $id = 0;
    $id++;

    $plan_name = 'Test Plan ' . $id;

    $plan_entity = $this->prophesize('\Drupal\ghi_plans\Entity\Plan');
    $plan_entity->id()->willReturn($id);
    $plan_entity->getShortName()->willReturn($plan_name);
    $plan_entity->getYear()->willReturn(2024);
    $plan_entity->getCacheTags()->willReturn(['plan:' . $id]);
    $plan_entity->hasField('field_footnotes')->willReturn(FALSE);
    $plan_entity->needsYear()->willReturn(FALSE);
    $plan_entity->canLinkToFts()->willReturn(FALSE);
    $plan_entity->toUrl(Argument::any())->willReturn(NULL);
    $plan_entity->getPlanStatusLabel()->willReturn('Published');

    $plan_type = $this->prophesize('\Drupal\ghi_plans\Entity\PlanType');
    $plan_type->id()->willReturn('hrp');
    $plan_type->label()->willReturn('Humanitarian Response Plan');
    $plan_type->getAbbreviation()->willReturn('HRP');

    $plan = $this->prophesize('\Drupal\ghi_plans\ApiObjects\Partials\PlanOverviewPlan');
    $plan->id()->willReturn($id);
    $plan->getName()->willReturn($plan_name);
    $plan->getEntity()->willReturn($plan_entity->reveal());
    $plan->getRequirements()->willReturn(2000000);
    $plan->getFunding()->willReturn(1000000);
    $plan->getCoverage(2)->willReturn(50.0);
    $plan->getCaseloadValue('in_need')->willReturn(100000);
    $plan->getCaseloadValue('target')->willReturn(80000);
    $plan->getCaseloadValue('latest_reach')->willReturn(60000);
    $plan->getCaseloadValue('expected_reach', 'Expected Reach')->willReturn(70000);
    $plan->getPlanType()->willReturn($plan_type->reveal());
    $plan->getTypeShortName()->willReturn('HRP');
    $plan->isPartOfGho()->willReturn(FALSE);
    $plan->getPlanDocumentUri()->willReturn(NULL);
    $plan->getPlanStatus()->willReturn(TRUE);
    $plan->getPlanStatusLabel()->willReturn('Published');

    return $plan;
  }

  /**
   * Mocks the plan overview query for the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanTable $plugin
   *   The PlanTable plugin instance.
   * @param array $plans
   *   An array of mocked plans.
   */
  private function mockPlanOverviewQuery(PlanTable $plugin, array $plans) {
    $plan_ids = array_map(function ($plan) {
      return $plan->id();
    }, $plans);

    $plans_query = $this->prophesize('\Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery');
    $plans_query->getPlans()->willReturn(array_combine($plan_ids, $plans));

    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setValue($plugin, [
      'plans_overview' => $plans_query->reveal(),
    ]);
  }

  /**
   * Mocks the empty plan overview query for the plugin.
   *
   * @param \Drupal\ghi_blocks\Plugin\Block\GlobalPage\PlanTable $plugin
   *   The PlanTable plugin instance.
   */
  private function mockEmptyPlanOverviewQuery(PlanTable $plugin) {
    $plans_query = $this->prophesize('\Drupal\ghi_plans\Plugin\FabricQuery\PlanOverviewQuery');
    $plans_query->getPlans()->willReturn([]);

    $reflection = new \ReflectionClass($plugin);
    $property = $reflection->getProperty('queryHandlers');
    $property->setValue($plugin, [
      'plans_overview' => $plans_query->reveal(),
    ]);
  }

}
