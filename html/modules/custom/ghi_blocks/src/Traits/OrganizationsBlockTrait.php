<?php

namespace Drupal\ghi_blocks\Traits;

use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_common\Helpers\ArrayHelper;

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
    $organizations = &drupal_static(__FUNCTION__, []);
    $cache_key = $this->getOrganizationCacheKey();
    if (!array_key_exists($cache_key, $organizations)) {
      $organizations[$cache_key] = [];
      foreach ($this->getProjects() as $project) {
        $organizations[$cache_key] += $project->getOrganizations();
      }
      ArrayHelper::sortObjectsByMethod($organizations[$cache_key], 'getName', SORT_ASC, SORT_STRING);
    }
    return $organizations[$cache_key];
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
    return $this->getProjectsByOrganization()[$organization->id()] ?? [];
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
    $clusters = &drupal_static(__FUNCTION__, []);
    $cache_key = $this->getOrganizationCacheKey($organization->id());
    if (!array_key_exists($cache_key, $clusters)) {
      $clusters[$cache_key] = [];
      foreach ($this->getOrganizationProjects($organization) as $project) {
        $clusters[$cache_key] += $project->getClusters();
      }
      ArrayHelper::sortObjectsByMethod($clusters[$cache_key], 'getName', SORT_ASC, SORT_STRING);
    }
    return $clusters[$cache_key];
  }

  /**
   * Get the projects grouped by organization.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  private function getProjectsByOrganization() {
    $projects_by_organization = &drupal_static(__FUNCTION__, []);
    $cache_key = $this->getOrganizationCacheKey();
    if (!array_key_exists($cache_key, $projects_by_organization)) {
      $projects_by_organization[$cache_key] = $this->getProjectQuery()->groupProjectsByOrganization($this->getProjects());
    }
    return $projects_by_organization[$cache_key];
  }

  /**
   * Get the projects for the current plan.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  private function getProjects() {
    $plan_object = $this->getCurrentPlanObject();
    $base_object = $this->getCurrentBaseObject();
    $projects = &drupal_static(__FUNCTION__, []);
    $cache_key = $this->getOrganizationCacheKey();
    if (!array_key_exists($cache_key, $projects)) {
      $projects[$cache_key] = $this->getProjectQuery()->getProjectsForPlanId($plan_object->getSourceId(), $base_object instanceof BaseObjectChildInterface ? $base_object : NULL);
    }
    return $projects[$cache_key];
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
   * Get a static cache key for the current plan and optional cluster context.
   *
   * @param int|null $organization_id
   *   An optional organization id.
   *
   * @return string
   *   The cache key.
   */
  private function getOrganizationCacheKey(?int $organization_id = NULL): string {
    $plan_object = $this->getCurrentPlanObject();
    $base_object = $this->getCurrentBaseObject();
    $parts = [
      $plan_object?->id(),
      $plan_object?->getSourceId(),
      $base_object instanceof BaseObjectChildInterface ? $base_object->id() : NULL,
      $base_object instanceof BaseObjectChildInterface ? $base_object->getSourceId() : NULL,
      $organization_id,
    ];
    return implode(':', array_map(fn ($part) => $part ?? 'none', $parts));
  }

}
