<?php

namespace Drupal\Tests\ghi_plans\Kernel\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;

/**
 * Tests the PlanProjectCluster API object.
 *
 * @group ghi_plans
 */
class PlanProjectClusterTest extends PlanApiObjectKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function createMockRawData(array $data_overrides = []): object {
    $plan_project_cluster_defaults = [
      'icon' => (object) ['class' => 'icon-test'],
    ];

    $merged_overrides = array_merge($plan_project_cluster_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanProjectCluster constructor and mapping.
   */
  public function testPlanProjectClusterConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Project Cluster',
      'value' => (object) [
        'icon' => 'health-icon',
      ],
    ]);

    $project_cluster = new PlanProjectCluster($raw_data);

    $this->assertApiObjectBasics($project_cluster, 'planprojectcluster', [
      'id',
      'name',
      'icon',
    ]);

    $this->assertEquals('health-icon', $project_cluster->getIcon());
    $this->assertEquals('health-icon', $project_cluster->icon);

    $this->assertEquals('planprojectcluster', $project_cluster->getBundle());
    $this->assertEquals('Test Project Cluster', $project_cluster->getName());
  }

  /**
   * Test null or empty data handling.
   */
  public function testNullOrEmptyDataHandling(): void {
    // Test with minimal data including null value object.
    $minimal_data = $this->createMockRawData([
      'id' => 1,
      'name' => '',
      'value' => (object) ['icon' => NULL],
    ]);
    $project_cluster = new PlanProjectCluster($minimal_data);

    $this->assertEquals(1, $project_cluster->id());
    $this->assertIsString($project_cluster->getName());
    $this->assertNull($project_cluster->getIcon());
  }

  /**
   * Test invalid data structure handling.
   */
  public function testInvalidDataStructureHandling(): void {
    // Test with minimal required data.
    $raw_data = $this->createMockRawData([
      'id' => 123,
      'name' => 'Test Cluster',
      'value' => (object) ['icon' => 'test-icon'],
    ]);
    $project_cluster = new PlanProjectCluster($raw_data);

    $this->assertEquals(123, $project_cluster->id());
  }

  /**
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData([
      'value' => (object) ['icon' => 'test-icon'],
    ]);
    $project_cluster = new PlanProjectCluster($raw_data);

    $cache_tags = $project_cluster->getCacheTags();
    $this->assertIsArray($cache_tags);

    $cache_contexts = $project_cluster->getCacheContexts();
    $this->assertIsArray($cache_contexts);

    $cache_max_age = $project_cluster->getCacheMaxAge();
    $this->assertIsInt($cache_max_age);
  }

  /**
   * Test getIcon method functionality.
   */
  public function testGetIconMethod(): void {
    $raw_data = $this->createMockRawData([
      'value' => (object) ['icon' => 'education-icon'],
    ]);
    $project_cluster = new PlanProjectCluster($raw_data);

    $this->assertEquals('education-icon', $project_cluster->getIcon());
  }

}
