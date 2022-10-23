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
    // Extract the cluster ids.
    $cluster_ids = property_exists($data, 'clusterId') ? [$data->clusterId] : [];
    if (empty($cluster_ids) && property_exists($data, 'governingEntities')) {
      $cluster_ids = array_map(function ($governing_entity) {
        return $governing_entity->id;
      }, $data->governingEntities);
    }
    $clusters = array_map(function ($governing_entity) {
      return new PlanProjectCluster($governing_entity);
    }, $data->governingEntities ?? []);

    return (object) [
      'id' => $data->id,
      'name' => $data->name,
      'version_code' => $data->versionCode,
      'cluster_ids' => $cluster_ids,
      'clusters' => $clusters,
      'global_clusters' => $data->globalClusters ?? [],
      // 'organizations' => $this->getOrganizations(),
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
   * @return array
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

}
