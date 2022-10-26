<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

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
 *       "excludeFields" = "plans,workflowStatusOptions,locations,planEntityIds",
 *       "includeFields" = "locationIds",
 *       "limit" = "1000",
 *     }
 *   }
 * )
 * @codingStandardsIgnoreEnd
 */
class PlanProjectSearchQuery extends EndpointQueryBase {

  use SimpleCacheTrait;

  /**
   * A list of cluster ids to be used as filters.
   *
   * @var array
   */
  protected $filterByClusterIds = NULL;

  /**
   * Set an array of cluster ids to apply as a filter or data retrieval.
   *
   * @param array $cluster_ids
   *   An array of cluster ids.
   */
  public function setFilterByClusterIds(array $cluster_ids) {
    $this->filterByClusterIds = $cluster_ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $placeholders = array_merge($placeholders, $this->getPlaceholders());
    $cache_key = $this->getCacheKey($placeholders + $query_args);
    if ($cached_data = $this->cache($cache_key)) {
      return $cached_data;
    }

    $data = parent::getData($placeholders, $query_args);
    if (empty($data) || !is_object($data) || !property_exists($data, 'results')) {
      return [];
    }
    $this->cache($cache_key, $data);
    return $data;
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
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($project_count = $this->cache($cache_key)) {
      return $project_count;
    }
    $project_count = count($this->getProjects($base_object));
    $this->cache($cache_key, $project_count);
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
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_count = $this->cache($cache_key)) {
      return $organization_count;
    }
    $organization_count = count($this->getOrganizations($base_object));
    $this->cache($cache_key, $organization_count);
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
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'organization' => $organization->id(),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_projects = $this->cache($cache_key)) {
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
    $this->cache($cache_key, $organization_projects);
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
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'organization' => $organization->id(),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organization_clusters = $this->cache($cache_key)) {
      return $organization_clusters;
    }
    $projects = $this->getProjects($base_object);
    $organization_clusters = [];
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if (empty($project->clusters)) {
        continue;
      }
      $organization_ids = array_keys($project->getOrganizations());
      if (in_array($organization->id, $organization_ids)) {
        $organization_clusters = array_merge($organization_clusters, $project->clusters);
      }
    }
    $this->cache($cache_key, $organization_clusters);
    return $organization_clusters;
  }

  /**
   * Get the organizations in the context of the given node.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An optonal array of projects from which the organizations should be
   *   extracted.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getOrganizations(BaseObjectInterface $base_object = NULL, array $projects = NULL) {
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($organizations = $this->cache($cache_key)) {
      return $organizations;
    }

    if (empty($projects)) {
      $projects = $this->getProjects($base_object);
    }
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
    $this->cache($cache_key, $organizations);
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
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
      'filter_unpublished' => $filter_unpublished ? 'true' : 'false',
    ]));
    if ($projects = $this->cache($cache_key)) {
      return $projects;
    }

    $data = $this->getData();
    if (empty($data) || !is_object($data)) {
      return [];
    }

    $projects = [];
    foreach ($data->results as $project) {
      if ($filter_unpublished && empty($project->currentPublishedVersionId)) {
        // Filter out unpublished projects.
        // Do this early to save ressources.
        continue;
      }
      $projects[] = new Project($project);
    }

    if (!empty($base_object) && $base_object->bundle() == 'governing_entity') {
      $context_original_id = $base_object->get('field_original_id')->value;
      $projects = array_filter($projects, function ($item) use ($context_original_id) {
        return in_array($context_original_id, $item->cluster_ids);
      });
    }

    if ($this->filterByClusterIds !== NULL) {
      $cluster_ids = $this->filterByClusterIds;
      $projects = array_filter($projects, function ($item) use ($cluster_ids) {
        return count(array_intersect($cluster_ids, $item->cluster_ids));
      });
    }
    $this->cache($cache_key, $projects);
    return $projects;
  }

  /**
   * Get the clusters grouped by organizations.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   * @param array $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the cluster id and the value is the cluster object as retrieved from
   *   the API.
   */
  public function getClustersByOrganization(BaseObjectInterface $base_object = NULL, array $projects = NULL) {
    $cache_key = $this->getCacheKey(array_filter([
      'plan_id' => $this->getPlaceholder('plan_id'),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($clusters = $this->cache($cache_key)) {
      return $clusters;
    }

    if (empty($projects)) {
      $projects = $this->getProjects($base_object);
    }
    $clusters = [];
    foreach ($projects as $project) {
      $project_organizations = $project->getOrganizations();
      if (empty($project_organizations)) {
        continue;
      }
      foreach ($project_organizations as $organization) {
        if (empty($clusters[$organization->id])) {
          $clusters[$organization->id] = [];
        }
        foreach ($project->clusters as $cluster) {
          if (!empty($clusters[$organization->id][$cluster->id])) {
            continue;
          }
          $clusters[$organization->id][$cluster->id] = $cluster;
        }
      }
    }
    $this->cache($cache_key, $clusters);
    return $clusters;
  }

  /**
   * Get the projects grouped by organizations.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  public function getProjectsByOrganization(BaseObjectInterface $base_object = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($base_object);
    }
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

  /**
   * Get the projects grouped by location.
   *
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectInterface $base_object
   *   The context base object.
   * @param array $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the location id, the value is an
   *   array of project ids associated with that location.
   */
  public function getProjectsByLocation(BaseObjectInterface $base_object = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($base_object);
    }
    $projects_by_location = [];
    foreach ($projects as $project) {
      if (empty($project->location_ids)) {
        continue;
      }
      foreach ($project->location_ids as $location_id) {
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
