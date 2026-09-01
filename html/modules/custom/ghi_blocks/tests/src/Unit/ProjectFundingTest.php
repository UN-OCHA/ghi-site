<?php

namespace Drupal\Tests\ghi_blocks\Unit;

use Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\ProjectFunding;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectFundingQuery;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the project funding configuration item.
 *
 * @group ghi_blocks
 *
 * @coversDefaultClass \Drupal\ghi_blocks\Plugin\ConfigurationContainerItem\ProjectFunding
 */
class ProjectFundingTest extends UnitTestCase {

  /**
   * Tests current requirements are summed from the loaded projects.
   *
   * @covers ::getValue
   * @covers ::getCurrentRequirementsForOrganization
   */
  public function testCurrentRequirementsUseLoadedProjects(): void {
    $organization = $this->createOrganization(1, 'Organization one');
    $other_organization = $this->createOrganization(2, 'Organization two');
    $projects = [
      $this->createProject(1, 100.0, [$organization]),
      $this->createProject(2, 250.0, [$organization, $other_organization]),
      $this->createProject(3, 500.0, [$other_organization]),
    ];

    $project_funding = new ProjectFunding([], 'project_funding', []);
    $project_funding->set('data_type', 'current_requirements');
    $project_funding->setContextValue('organization', $organization);
    $project_funding->setContextValue('projects', $projects);
    $project_funding->projectFundingQuery = $this->createMock(PlanProjectFundingQuery::class);
    $project_funding->projectFundingQuery->expects($this->never())
      ->method('getSumForOrganization');

    $this->assertSame(350.0, $project_funding->getValue());
  }

  /**
   * Creates an organization API object.
   *
   * @param int $id
   *   The organization ID.
   * @param string $name
   *   The organization name.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization
   *   The organization API object.
   */
  private function createOrganization(int $id, string $name): Organization {
    return new Organization((object) [
      'Id' => $id,
      'Name' => $name,
    ]);
  }

  /**
   * Creates a project API object.
   *
   * @param int $id
   *   The project ID.
   * @param float $requirements
   *   The current project requirements.
   * @param \Drupal\ghi_plans\ApiObjects\Organization[] $organizations
   *   The project's organizations.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project
   *   The project API object.
   */
  private function createProject(int $id, float $requirements, array $organizations): Project {
    $organization_ids = array_map(fn (Organization $organization): int => $organization->id(), $organizations);
    return new Project((object) [
      'Id' => $id,
      'PlanId' => 1021,
      'Name' => 'Project ' . $id,
      'ProjectCode' => (string) $id,
      'IsPublished' => TRUE,
      'TotalProjectTarget' => 0,
      'CurrentRequestedFunds' => $requirements,
      'clusters' => [],
      'locationIds' => [],
      'organizations' => array_combine($organization_ids, $organizations),
    ]);
  }

}
