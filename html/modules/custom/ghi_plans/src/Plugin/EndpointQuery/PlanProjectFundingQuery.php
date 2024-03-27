<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Traits\ProjectTrait;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan funding summary.
 *
 * @EndpointQuery(
 *   id = "plan_project_funding_query",
 *   label = @Translation("Plan funding summary query"),
 *   endpoint = {
 *     "public" = "fts/project/plan",
 *     "version" = "v1",
 *     "query" = {
 *       "planid" = "{plan_id}",
 *       "groupBy" = "project"
 *     }
 *   }
 * )
 */
class PlanProjectFundingQuery extends EndpointQueryBase {

  use ProjectTrait;

  /**
   * This holds the processed data.
   *
   * @var array
   */
  private $data = NULL;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $placeholders = array_merge($placeholders, $this->getPlaceholders());
    $cache_key = $this->getCacheKey($placeholders);
    if ($cached_data = $this->getCache($cache_key)) {
      return $cached_data;
    }
    $data = (object) parent::getData($placeholders, $query_args);
    if (empty($data->report3) && empty($data->requirements)) {
      return [];
    }
    $funding = [];
    foreach ($data->report3->fundingTotals->objects[0]->singleFundingObjects ?? [] as $project_funding) {
      if (!property_exists($project_funding, 'id')) {
        continue;
      }
      $funding[$project_funding->id] = [
        'total_funding' => $project_funding->totalFunding,
      ];
    }
    foreach ($data->requirements->objects as $project_requirements) {
      $project_id = $project_requirements->id;
      if (empty($funding[$project_id])) {
        $funding[$project_id] = [
          'total_funding' => 0,
        ];
      }
      $funding[$project_id]['current_requirements'] = $project_requirements->revisedRequirements;
      $funding[$project_id]['original_requirements'] = $project_requirements->origRequirements;
      $funding[$project_id]['coverage'] = $project_requirements->revisedRequirements > 0 ? (100 / $project_requirements->revisedRequirements) * $funding[$project_id]['total_funding'] : 0;
    }

    $this->data = $funding;
    $this->setCache($cache_key, $this->data);
    return $this->data;
  }

  /**
   * Get a specific property for a project from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param \Drupal\ghi_plans\ApiObjects\Project $project
   *   The project for which to retrieve the property value.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property value or a default value.
   */
  public function getPropertyForProject($property, Project $project, $default = 0) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    if (empty($this->data[$project->id])) {
      return $default;
    }
    return !empty($this->data[$project->id][$property]) ? $this->data[$project->id][$property] : $default;
  }

  /**
   * Get the sum of a property for an organization from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param object $organization
   *   The organizations for which to retrieve the sum of the property value.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getSumForOrganization($property, $organization, array $projects) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    $property_values = [];
    $organization_projects = $this->filterProjectsToOrganization($projects, $organization);
    foreach ($organization_projects as $project) {
      if (empty($this->data[$project->id])) {
        continue;
      }
      $property_values[] = !empty($this->data[$project->id][$property]) ? $this->data[$project->id][$property] : 0;
    }

    $property_sum = !empty($property_values) ? array_sum($property_values) : 0;
    return $property_sum;
  }

  /**
   * Get the average of a property for an organization from the result set.
   *
   * @param object $organization
   *   The organizations for which to retrieve the sum of the property value.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getFundingCoverageForOrganization($organization, array $projects) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    $total_funding = 0;
    $requirements = 0;
    $organization_projects = $this->filterProjectsToOrganization($projects, $organization);
    foreach ($organization_projects as $project) {
      if (empty($this->data[$project->id])) {
        continue;
      }
      $funding = $this->data[$project->id];
      $total_funding += $funding['total_funding'] ?? 0;
      $requirements += $funding['current_requirements'] ?? 0;
    }

    return !empty($requirements) ? $total_funding / $requirements : 0;
  }

  /**
   * Get the average of a property for an organization from the result set.
   *
   * @param object $organization
   *   The organizations for which to retrieve the sum of the property value.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getRequirementsChangesForOrganization($organization, array $projects) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    $original_requirements = 0;
    $current_requirements = 0;
    $organization_projects = $this->filterProjectsToOrganization($projects, $organization);
    foreach ($organization_projects as $project) {
      if (empty($this->data[$project->id])) {
        continue;
      }
      $funding = $this->data[$project->id];
      $original_requirements += $funding['original_requirements'] ?? 0;
      $current_requirements += $funding['current_requirements'] ?? 0;
    }
    return $current_requirements - $original_requirements;
  }

  /**
   * Filter the projects array for the given organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   The projects to filter.
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization to filter for.
   *
   * @return object[]
   *   The filtered projects array.
   */
  private function filterProjectsToOrganization(array $projects, Organization $organization) {
    // The grouping is expensive, especially on plans with a high number of
    // projects. So we better cache this.
    // We assume here, that the projects array is the same for every invocation
    // of this function. If that changes, this code must be updated.
    $projects_by_organization = &drupal_static(static::class . '::' . __FUNCTION__, NULL);
    if ($projects_by_organization === NULL) {
      $projects_by_organization = $this->groupProjectsByOrganization($projects);
    }
    return $projects_by_organization[$organization->id()] ?? [];
  }

}
