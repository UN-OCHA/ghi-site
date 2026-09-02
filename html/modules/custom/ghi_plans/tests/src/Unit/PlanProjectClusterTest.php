<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;

/**
 * Tests the PlanProjectCluster API object.
 *
 * @group ghi_plans
 */
class PlanProjectClusterTest extends ApiObjectTestBase {

  /**
   * Test PlanProjectCluster constructor and mapping.
   */
  public function testPlanProjectClusterConstructorAndMapping(): void {
    $project_cluster = new PlanProjectCluster((object) [
      'Id' => 123,
      'Name' => 'Test Project Cluster',
      'icon' => (object) ['Name' => 'health-icon'],
    ]);
    $this->assertApiObjectBasics($project_cluster, 'planprojectcluster');
    $this->assertEquals('health-icon', $project_cluster->getIcon());
  }

}
