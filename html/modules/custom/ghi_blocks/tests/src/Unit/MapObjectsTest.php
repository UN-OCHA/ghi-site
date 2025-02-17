<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\ghi_blocks\MapObjects\ClusterMapObject;
use Drupal\ghi_blocks\MapObjects\OrganizationMapObject;
use Drupal\ghi_blocks\MapObjects\ProjectMapObject;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\ApiObjects\Project;

/**
 * Test map objects.
 *
 * @group MapObjects
 */
class MapObjectsTest extends UnitTestCase {

  /**
   * Tests that base object components can be retrieved from sections.
   */
  public function testClusterMapObjects() {
    $cluster_map_object = new ClusterMapObject(1, 'Test cluster', [1, 2, 3], [
      'icon' => 'test-icon',
    ]);
    $this->assertSame(1, $cluster_map_object->id());
    $this->assertSame('Test cluster', $cluster_map_object->getName());
    $this->assertSame([1, 2, 3], $cluster_map_object->getLocationIds());
    $this->assertSame('test-icon', $cluster_map_object->getIcon());
  }

  /**
   * Tests that base object components can be retrieved from sections.
   */
  public function testOrganizationMapObjects() {
    $project = new Project((object) [
      'id' => 1,
      'name' => 'Test project',
      'versionCode' => '1.0',
      'currentPublishedVersionId' => '1.0',
      'currentRequestedFunds' => 100,
      'locationIds' => (object) ['ids' => []],
    ]);
    $organization_map_object = new OrganizationMapObject(1, 'Test organization', [1, 2, 3], [
      'projects' => [$project],
    ]);
    $this->assertSame(1, $organization_map_object->id());
    $this->assertSame('Test organization', $organization_map_object->getName());
    $this->assertSame([1, 2, 3], $organization_map_object->getLocationIds());
    $this->assertSame([$project], $organization_map_object->getProjects());
  }

  /**
   * Tests that base object components can be retrieved from sections.
   */
  public function testProjectMapObjects() {
    $cluster = new PlanProjectCluster((object) [
      'id' => 1,
      'name' => 'Test cluster',
      'value' => (object) ['icon' => 'test-icon'],
    ]);
    $project_map_object = new ProjectMapObject(1, 'Test project', [1, 2, 3], [
      'clusters' => [$cluster],
    ]);
    $this->assertSame(1, $project_map_object->id());
    $this->assertSame('Test project', $project_map_object->getName());
    $this->assertSame([1, 2, 3], $project_map_object->getLocationIds());
    $this->assertSame([$cluster], $project_map_object->getClusters());
  }

}
