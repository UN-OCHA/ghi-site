<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Traits\ProjectTrait;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for project search.
 *
 * @codingStandardsIgnoreStart
 * @EndpointQuery(
 *   id = "plan_project_search_query",
 *   label = @Translation("Plan project search query"),
 *   endpoint = {
 *     "public" = "public/project/search",
 *     "version" = "v2",
 *     "query" = {
 *       "planIds" = "{plan_id}",
 *       "latest" = "true",
 *       "excludeFields" = "plans,workflowStatusOptions,locations,planEntityIds,globalClusters",
 *       "includeFields" = "locationIds",
 *       "limit" = "2000",
 *     }
 *   }
 * )
 * @codingStandardsIgnoreEnd
 */
class PlanProjectSearchQuery extends EndpointQueryBase {

  use ProjectTrait;

  /**
   * A list of cluster ids to be used as filters.
   *
   * @var array
   */
  protected $filterByClusterIds = NULL;

  /**
   * Set an array of cluster ids to apply as a filter after data retrieval.
   *
   * @param array $cluster_ids
   *   An array of cluster ids.
   */
  public function setFilterByClusterIds(array $cluster_ids) {
    $this->filterByClusterIds = $cluster_ids;
  }

  /**
   * Set a cluster context to use for the data retrieval.
   *
   * @param int $id
   *   The id of a plan cluster.
   */
  public function setClusterContext($id) {
    $this->endpointQuery->setEndpointArgument('governingEntityIds', $id);
  }

  /**
   * Get the cluster context if it is set.
   *
   * @return int|null
   *   The id of a plan cluster or NULL.
   */
  public function getClusterContext() {
    return $this->endpointQuery->getEndpointArgument('governingEntityIds');
  }

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $placeholders += $this->getPlaceholders();
    if (!$this->getPlaceholder('plan_id') || empty($placeholders['plan_id'])) {
      return NULL;
    }
    $data = parent::getData($placeholders, $query_args);
    if (empty($data) || !is_object($data) || !property_exists($data, 'results')) {
      return [];
    }
    return $data;
  }

  /**
   * Get cache keys common to all functions.
   *
   * These depend on request specific context.
   *
   * @return array
   *   An array of common cache keys.
   */
  private function getCommonCacheKeys() {
    return [
      'plan_id' => $this->getPlaceholder('plan_id'),
      'cluster_context' => $this->getClusterContext() ? $this->getClusterContext() : NULL,
      'cluster_filter' => $this->filterByClusterIds ? implode(':', $this->filterByClusterIds) : NULL,
    ];
  }

  /**
   * Get the number of projects in the context of the given node.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return int
   *   The number of projects.
   */
  public function getProjectCount(BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($project_count = $this->getCache($cache_key)) {
      return $project_count;
    }
    $project_count = count($this->getProjects($base_object));
    $this->setCache($cache_key, $project_count);
    return $project_count;
  }

  /**
   * Get the number of organizations in the context of the given node.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return int
   *   The number of organizations.
   */
  public function getOrganizationCount(BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_count = $this->getCache($cache_key)) {
      return $organization_count;
    }
    $organization_count = count($this->getOrganizations($base_object));
    $this->setCache($cache_key, $organization_count);
    return $organization_count;
  }

  /**
   * Get the projects for an organization.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization for which to look up the projects.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects for the given organization.
   */
  public function getOrganizationProjects(Organization $organization, BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'organization' => $organization->id(),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_projects = $this->getCache($cache_key)) {
      return $organization_projects;
    }

    $projects = $this->getProjects($base_object);
    $organization_projects = [];
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if ($project->hasOrganization($organization)) {
        $organization_projects[$project->id] = $project;
      }
    }
    $this->setCache($cache_key, $organization_projects);
    return $organization_projects;
  }

  /**
   * Get the clusters for an organization.
   *
   * @param object $organization
   *   The organization for which to look up the projects.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of cluster objects for the given organization.
   */
  public function getOrganizationClusters($organization, BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'organization' => $organization->id(),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_clusters = $this->getCache($cache_key)) {
      return $organization_clusters;
    }
    $projects = $this->getProjects($base_object);
    $organization_clusters = [];
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if (empty($project->getClusters())) {
        continue;
      }
      $organization_ids = array_keys($project->getOrganizations());
      if (in_array($organization->id, $organization_ids)) {
        $organization_clusters = array_merge($organization_clusters, $project->getClusters());
      }
    }
    $this->setCache($cache_key, $organization_clusters);
    return $organization_clusters;
  }

  /**
   * Get the organizations in the context of the given node.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getOrganizations(BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organizations = $this->getCache($cache_key)) {
      return $organizations;
    }

    $projects = $this->getProjects($base_object);

    $organizations = [];
    if (empty($projects) || !is_array($projects)) {
      return $organizations;
    }
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      $project_organizations = $project->getOrganizations();
      if (empty($project_organizations)) {
        continue;
      }
      foreach ($project_organizations as $organization) {
        if (!empty($organizations[$organization->id])) {
          continue;
        }
        $organizations[$organization->id] = $organization;

      }
    }

    // Alphabetical sort of Organizations.
    uasort($organizations, function ($a, $b) {
      return strcmp($a->name, $b->name);
    });

    $this->setCache($cache_key, $organizations);
    return $organizations;
  }

  /**
   * Get the projects in the context of the given base object.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   * @param bool $filter_unpublished
   *   Whether unpublished projects should be filtered.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjects(BaseObjectInterface $base_object = NULL, $filter_unpublished = TRUE) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
      'filter_unpublished' => $filter_unpublished ? 'true' : 'false',
    ]));
    $projects = $this->getCache($cache_key);
    if (is_array($projects)) {
      return $projects;
    }

    $data = $this->getData();
    if (empty($data) || !is_object($data)) {
      $this->setCache($cache_key, []);
      return [];
    }

    $projects = [];
    foreach ($data->results as $project) {
      if ($filter_unpublished && empty($project->currentPublishedVersionId)) {
        // Filter out unpublished projects.
        // Do this early to save ressources.
        continue;
      }
      $project_object = new Project($project);
      $project_object->setCacheTags([
        'plan_id:' . $this->getPlaceholder('plan_id'),
      ]);
      $projects[] = $project_object;
    }

    if (!empty($base_object) && $base_object instanceof GoverningEntity) {
      $context_original_id = $base_object->getSourceId();
      $projects = array_filter($projects, function (Project $item) use ($context_original_id) {
        return in_array($context_original_id, $item->getClusterIds());
      });
    }

    if ($this->filterByClusterIds !== NULL) {
      $cluster_ids = $this->filterByClusterIds;
      $projects = array_filter($projects, function (Project $item) use ($cluster_ids) {
        return count(array_intersect($cluster_ids, $item->getClusterIds()));
      });
    }
    $this->setCache($cache_key, $projects);
    return $projects;
  }

  /**
   * Get the clusters grouped by organizations.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the cluster id and the value is the cluster object as retrieved from
   *   the API.
   */
  public function getClustersByOrganization(BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter($this->getCommonCacheKeys() + [
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($clusters = $this->getCache($cache_key)) {
      return $clusters;
    }

    $projects = $this->getProjects($base_object);
    $clusters = [];
    foreach ($projects as $project) {
      $project_organizations = $project->getOrganizations();
      if (empty($project_organizations)) {
        continue;
      }
      foreach ($project_organizations as $organization) {
        if (empty($clusters[$organization->id()])) {
          $clusters[$organization->id()] = [];
        }
        foreach ($project->getClusters() as $cluster) {
          if (!empty($clusters[$organization->id()][$cluster->id()])) {
            continue;
          }
          $clusters[$organization->id()][$cluster->id()] = $cluster;
        }
      }
    }
    $this->setCache($cache_key, $clusters);
    return $clusters;
  }

  /**
   * Get the projects grouped by organizations.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  public function getProjectsByOrganization(BaseObjectInterface $base_object = NULL) {
    $projects = $this->getProjects($base_object);
    $organization_projects = $this->groupProjectsByOrganization($projects);
    return $organization_projects;
  }

  /**
   * Get the projects grouped by location.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   *
   * @return array[]
   *   An array of arrays. First level key is the location id, the value is an
   *   array of project ids associated with that location.
   */
  public function getProjectsByLocation(BaseObjectInterface $base_object = NULL) {
    $projects = $this->getProjects($base_object);
    $projects_by_location = [];
    foreach ($projects as $project) {
      if (empty($project->getLocationIds())) {
        continue;
      }
      foreach ($project->getLocationIds() as $location_id) {
        if (empty($projects_by_location[$location_id])) {
          $projects_by_location[$location_id] = [];
        }
        if (!in_array($project->id, $projects_by_location[$location_id])) {
          $projects_by_location[$location_id][] = $project->id;
        }
      }
    }
    return $projects_by_location;
  }

}
