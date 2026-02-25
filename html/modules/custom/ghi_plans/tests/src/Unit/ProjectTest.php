<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;

/**
 * Tests the Project API object.
 *
 * @group ghi_plans
 */
class ProjectTest extends ApiObjectTestBase {

  /**
   * Test Project constructor and mapping.
   */
  public function testProjectConstructorAndMapping(): void {
    $project = $this->getProjectFromFixture(197792);
    $this->assertApiObjectBasics($project, 'project');

    // Test Project-specific properties.
    $this->assertEquals(197792, $project->id());
    $this->assertEquals('Reducing Protection Risks and Meeting Basic Needs of Conflict-Affected Communities Far North, North West and South West Regions, Cameroon', $project->getName());

    $this->assertTrue($project->isPublished());
    $this->assertNull($project->getPlanId());
    $this->assertEquals('HCMR23-FSC;PRO;WSH-197792-1', $project->getProjectCode());
    $this->assertEquals(2437563, $project->getRequirements());
    $this->assertIsArray($project->getOrganizations());
    $this->assertEmpty($project->getOrganizations());
    $this->assertIsArray($project->getClusters());
    $this->assertEmpty($project->getClusters());
    $this->assertIsArray($project->getClusterIds());
    $this->assertEmpty($project->getClusterIds());
    $this->assertIsArray($project->getLocationIds());
    $this->assertEquals([], $project->getLocationIds());

    $organization = $this->prophesize(Organization::class)->reveal();
    $this->assertFalse($project->hasOrganization($organization));
  }

  /**
   * Load a project from the fixtures.
   *
   * @param string $id
   *   The id of the project ficture.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project
   *   The project object.
   */
  private function getProjectFromFixture($id): Project {
    $data = $this->getApiObjectFixture('Project', $id);
    $this->assertNotEmpty($data);
    return new Project($data);
  }

}
