<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;

/**
 * Helper trait for block plugins showing organization data.
 */
trait OrganizationsBlockTrait {

  use PlanQueryTrait;

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
    $plan_object = $this->getCurrentPlanObject();
    $base_object = $this->getCurrentBaseObject();
    $organizations = &drupal_static(__FUNCTION__, []);
    if (empty($organizations)) {
      $organizations = $this->getProjectQuery()->getProjectOrganizationsForPlan($plan_object, $base_object instanceof BaseObjectChildInterface ? $base_object : NULL);
    }
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
    if (empty($projects[$organization->id()])) {
      $projects[$organization->id()] = $this->getProjectQuery()->getProjectsForPlanId($plan_object->getSourceId(), NULL, $organization->id());
    }
    return $projects[$organization->id()];
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
    if (empty($clusters[$organization->id()])) {
      $clusters[$organization->id()] = $this->getProjectQuery()->getProjectClustersForPlan($plan_object, NULL, $organization->id());
    }
    return $clusters[$organization->id()];
  }

  /**
   * Get the projects grouped by organization.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  private function getProjectsByOrganization(?array $organizations = NULL) {
    $plan_object = $this->getCurrentPlanObject();
    return $this->getProjectQuery()->getPlanProjectsByOrganization($plan_object);
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

}
