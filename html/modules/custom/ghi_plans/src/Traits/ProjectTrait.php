<?php

namespace Drupal\ghi_plans\Traits;

/**
 * Trait to help with projects.
 */
trait ProjectTrait {

  /**
   * Group the given array of projects by organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An array of projects that should be grouped by organization.
   *
   * @return array[]
   *   An array of organizations, keyed by organization id, holding the
   *   projects for that oganization as values.
   */
  public static function groupProjectsByOrganization(array $projects) {
    $organization_projects = [];
    foreach ($projects as $project) {
      $project_organizations = $project->getOrganizations();
      if (empty($project_organizations)) {
        continue;
      }
      foreach ($project_organizations as $organization) {
        if (empty($organization_projects[$organization->id])) {
          $organization_projects[$organization->id] = [];
        }
        $organization_projects[$organization->id][$project->id] = $project;
      }
    }
    return $organization_projects;
  }

}
