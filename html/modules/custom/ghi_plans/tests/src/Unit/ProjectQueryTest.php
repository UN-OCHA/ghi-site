<?php

namespace Drupal\Tests\ghi_plans\Unit;

use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery;
use Drupal\hpc_api\Query\FabricClient;
use Drupal\hpc_api\Query\FabricQuery;
use Drupal\Tests\hpc_api\Traits\PrivateAccessorTrait;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the project Fabric query plugin.
 *
 * @group ghi_plans
 *
 * @coversDefaultClass \Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery
 */
class ProjectQueryTest extends UnitTestCase {

  use PrivateAccessorTrait;

  /**
   * Tests that a governing entity limits the Fabric project query.
   *
   * @covers ::getProjectsForPlanId
   */
  public function testGetProjectsForPlanIdFiltersByGoverningEntity(): void {
    $query_record = (object) ['filters' => []];
    $fabric_client = $this->createMock(FabricClient::class);
    $fabric_client->expects($this->once())
      ->method('createQuery')
      ->with('projects', Project::getGraphQlItems())
      ->willReturn($this->mockProjectFabricQuery($query_record));

    $context = $this->createMock(GoverningEntity::class);
    $context->method('getSourceId')->willReturn(5958);

    $project_query = $this->createProjectQuery($fabric_client);
    $projects = $project_query->getProjectsForPlanId(1021, $context);

    $this->assertSame([], $projects);
    $this->assertSame([
      'PlanId' => 1021,
      'IsPublished' => TRUE,
      'coordinationEntity' => ['Id' => 5958],
    ], $query_record->filters);
  }

  /**
   * Tests that an unscoped project query remains plan-wide.
   *
   * @covers ::getProjectsForPlanId
   */
  public function testGetProjectsForPlanIdWithoutContextRemainsPlanWide(): void {
    $query_record = (object) ['filters' => []];
    $fabric_client = $this->createMock(FabricClient::class);
    $fabric_client->expects($this->once())
      ->method('createQuery')
      ->with('projects', Project::getGraphQlItems())
      ->willReturn($this->mockProjectFabricQuery($query_record));

    $project_query = $this->createProjectQuery($fabric_client);
    $projects = $project_query->getProjectsForPlanId(1021);

    $this->assertSame([], $projects);
    $this->assertSame([
      'PlanId' => 1021,
      'IsPublished' => TRUE,
    ], $query_record->filters);
  }

  /**
   * Creates the project query service with a mocked Fabric client.
   *
   * @param \Drupal\hpc_api\Query\FabricClient $fabric_client
   *   The mocked Fabric client.
   *
   * @return \Drupal\ghi_plans\Plugin\FabricQuery\ProjectQuery
   *   The project query service.
   */
  private function createProjectQuery(FabricClient $fabric_client): ProjectQuery {
    $project_query = new ProjectQuery([], 'project', []);
    $this->setPrivateProperty($project_query, 'fabricClient', $fabric_client);
    return $project_query;
  }

  /**
   * Creates an empty Fabric project query and records its filters.
   *
   * @param object $query_record
   *   The query record that receives the filters.
   *
   * @return \Drupal\hpc_api\Query\FabricQuery
   *   The mocked Fabric project query.
   */
  private function mockProjectFabricQuery(object $query_record): FabricQuery {
    $query = $this->getMockBuilder(FabricQuery::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['setFilters', 'setOrderBy', 'execute'])
      ->getMock();

    $query->method('setFilters')
      ->willReturnCallback(function (array $filters) use ($query_record, $query): FabricQuery {
        $query_record->filters = $filters;
        return $query;
      });
    $query->method('setOrderBy')->willReturnSelf();
    $query->method('execute')->willReturn([]);

    return $query;
  }

}
