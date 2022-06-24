<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_plans\ApiObjects\Organization;

/**
 * Helper trait for block plugins showing organization data.
 */
trait OrganizationsBlockTrait {

  /**
   * Get the configured organizations.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  private function getConfiguredOrganizations() {
    $conf = $this->getBlockConfig();
    $organizations = $this->getOrganizations();
    if (empty($conf['organizations']['organization_ids']) || empty(array_filter($conf['organizations']['organization_ids']))) {
      return $organizations;
    }
    return array_intersect_key($organizations, array_flip(array_filter($conf['organizations']['organization_ids'])));
  }

  /**
   * Get all organizations for the current context.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   Array of organization objects as returned by the API.
   */
  private function getOrganizations() {
    $query = $this->getProjectSearchQuery();
    $organizations = $query->getOrganizations();
    uasort($organizations, function ($a, $b) {
      return strcmp($a->name, $b->name);
    });
    return $organizations;
  }

  /**
   * Get the projects for the given organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  private function getOrganizationProjects(Organization $organization) {
    $plan_object = $this->getCurrentPlanObject();
    $projects = &drupal_static(__FUNCTION__, []);
    if (empty($projects[$organization->id])) {
      $query = $this->getProjectSearchQuery();
      $projects[$organization->id] = $query->getOrganizationProjects($organization, $plan_object);
    }
    return $projects[$organization->id];
  }

  /**
   * Get the clusters for the given organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of cluster partial objects.
   */
  private function getOrganizationClusters(Organization $organization) {
    $plan_object = $this->getCurrentPlanObject();
    $clusters = &drupal_static(__FUNCTION__, []);
    if (empty($clusters[$organization->id])) {
      $query = $this->getProjectSearchQuery();
      $clusters[$organization->id] = $query->getOrganizationClusters($organization, $plan_object);
    }
    return $clusters[$organization->id];
  }

  /**
   * Get the project search query.
   *
   * @return \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery
   *   The project search query.
   */
  private function getProjectSearchQuery() {
    /** @var \Drupal\ghi_plans\Plugin\EndpointQuery\PlanProjectSearchQuery $query */
    $query = $this->getQueryHandler('project_search');
    return $query;
  }

}
