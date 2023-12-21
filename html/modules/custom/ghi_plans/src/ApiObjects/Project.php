<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction class for API project objects.
 */
class Project extends BaseObject {

  use SimpleCacheTrait;

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    // Extract the clusters.
    $clusters = [];
    foreach ($data->governingEntities ?? [] as $governing_entity) {
      $project_cluster = new PlanProjectCluster($governing_entity);
      $clusters[$project_cluster->id()] = $project_cluster;
    }

    return (object) [
      'id' => $data->id,
      'name' => $data->name,
      'version_code' => $data->versionCode,
      'clusters' => $clusters,
      'published' => !empty($data->currentPublishedVersionId),
      'requirements' => $data->currentRequestedFunds,
      'location_ids' => $data->locationIds->ids ?? [],
      'target' => !empty($data->targets) ? array_sum(array_map(function ($item) {
        return $item->total;
      }, $data->targets)) : 0,
    ];
  }

  /**
   * Process organization objects from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of processed organization objects.
   */
  public function getOrganizations() {
    $cache_key = $this->getCacheKey(['project_id' => $this->id()]);
    $processed_organizations = $this->cache($cache_key);
    if ($processed_organizations) {
      return $processed_organizations;
    }

    $data = $this->getRawData();
    $processed_organizations = [];
    // First find the organizations. There are 2 ways.
    $project_organizations = !empty($data->organizations) ? $data->organizations : [];
    if (property_exists($data, 'projectVersions')) {
      $project_version = array_filter($data->projectVersions, function ($item) use ($data) {
        return $item->id == $data->currentPublishedVersionId && !empty($item->organizations);
      });
      $project_organizations = $project_version->organizations;
    }

    // Now process the organizations.
    foreach ($project_organizations as $organization) {
      if (!empty($processed_organizations[$organization->id])) {
        continue;
      }
      $processed_organizations[$organization->id] = new Organization($organization);
    }
    $this->cache($cache_key, $processed_organizations);
    return $processed_organizations;
  }

  /**
   * Get the project clusters.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of clusters for this project.
   */
  public function getClusters() {
    return $this->clusters ?? [];
  }

  /**
   * Get the project cluster ids.
   *
   * @return int[]
   *   An array of cluster ids for this project.
   */
  public function getClusterIds() {
    return array_keys($this->getClusters() ?? []);
  }

  /**
   * Get the project location ids.
   *
   * @return int[]
   *   An array of location ids for this project.
   */
  public function getLocationIds() {
    return $this->location_ids;
  }

  /**
   * Check if this project has the given organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization to check for.
   *
   * @return bool
   *   TRUE if the current project lists the given organization, FALSE
   *   otherwise.
   */
  public function hasOrganization(Organization $organization) {
    $organizations = $this->getOrganizations();
    return array_key_exists($organization->id, $organizations);
  }

}
