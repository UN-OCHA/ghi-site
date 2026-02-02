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
      'Id' => 123,
      'Name' => 'Test Cluster',
      'Icon' => 'icon-test',
    ];

    $merged_overrides = array_merge($plan_project_cluster_defaults, $data_overrides);
    return parent::createMockRawData($merged_overrides);
  }

  /**
   * Test PlanProjectCluster constructor and mapping.
   */
  public function testPlanProjectClusterConstructorAndMapping(): void {
    $raw_data = $this->createMockRawData([
      'Id' => 123,
      'Name' => 'Test Project Cluster',
      'Icon' => 'health-icon',
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
   * Test cache tags and dependencies.
   */
  public function testCacheTagsAndDependencies(): void {
    $raw_data = $this->createMockRawData([
      'Icon' => 'test-icon',
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
      'Icon' => 'education-icon',
    ]);
    $project_cluster = new PlanProjectCluster($raw_data);

    $this->assertEquals('education-icon', $project_cluster->getIcon());
  }

}
