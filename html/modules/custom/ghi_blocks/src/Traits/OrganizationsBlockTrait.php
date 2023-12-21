<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\Entity\GoverningEntity;

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
   * Get the projects grouped by organization.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  private function getProjectsByOrganization() {
    $query = $this->getProjectSearchQuery();
    return $query->getProjectsByOrganization();
  }

  /**
   * Get the clusters grouped by organization.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the cluster id and the value is a cluster object.
   */
  private function getClustersByOrganization() {
    $query = $this->getProjectSearchQuery();
    return $query->getClustersByOrganization();
  }

  /**
   * Get the current cluster context.
   *
   * @return Drupal\ghi_plans\Entity\GoverningEntity|null
   *   A governing entity (cluster) or NULL.
   */
  private function getClusterContext() {
    $base_object = $this->getCurrentBaseObject();
    return $base_object && $base_object instanceof GoverningEntity ? $base_object : NULL;
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
    if ($cluster_context = $this->getClusterContext()) {
      $query->setClusterContext($cluster_context->getSourceId());
    }
    return $query;
  }

}
