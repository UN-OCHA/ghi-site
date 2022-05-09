<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan funding summary.
 *
 * @EndpointQuery(
 *   id = "plan_project_funding_query",
 *   label = @Translation("Plan funding summary query"),
 *   endpoint = {
 *     "public" = "fts/project/plan?planid={plan_id}&groupBy=project",
 *     "version" = "v1"
 *   }
 * )
 */
class PlanProjectFundingQuery extends EndpointQueryBase {

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
    if ($cached_data = $this->cache($cache_key)) {
      return $cached_data;
    }
    $data = (object) parent::getData($placeholders, $query_args);
    if (empty($data->report3) && empty($data->requirements)) {
      return [];
    }
    $funding = [];
    foreach ($data->report3->fundingTotals->objects[0]->singleFundingObjects as $project_funding) {
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
    $this->cache($cache_key, $this->data);
    return $this->data;
  }

  /**
   * Get a specific property for a project from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param object $project
   *   The project for which to retrieve the property value.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property value or a default value.
   */
  public function getPropertyForProject($property, $project, $default = 0) {
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
   * @param object $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getSumForOrganization($property, $organization, $projects) {
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
   * @param object $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getFundingCoverageForOrganization($organization, $projects) {
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
   * @param object $projects
   *   The projects to which the organization can belong.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getRequirementsChangesForOrganization($organization, $projects) {
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
   * @param object $projects
   *   The projects to filter.
   * @param object $organization
   *   The organizations to filter for.
   *
   * @return object[]
   *   The filtered projects array.
   */
  private function filterProjectsToOrganization($projects, $organization) {
    $filtered_projects = [];
    foreach ($projects as $project) {
      if (empty($project->organizations)) {
        continue;
      }
      $organization_ids = array_map(function ($_organization) {
        return $_organization->id;
      }, $project->organizations);

      if (in_array($organization->id, $organization_ids)) {
        $filtered_projects[] = $project;
      }
    }
    return $filtered_projects;
  }

}
