<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for project search.
 *
 * @EndpointQuery(
 *   id = "plan_project_search_query",
 *   label = @Translation("Plan project search query"),
 *   endpoint = {
 *     "public" = "public/project/search",
 *     "version" = "v2",
 *     "query" = {
 *       "planIds" = "{plan_id}",
 *       "latest" = "true",
 *       "excludeFields" = "plans,workflowStatusOptions,locations",
 *       "includeFields" = "locationIds,planEntityIds",
 *       "limit" = "1000",
 *     }
 *   }
 * )
 */
class PlanProjectSearchQuery extends EndpointQueryBase {

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
    $cache_key = $this->getCacheKey($placeholders);
    if ($cached_data = $this->cache($cache_key)) {
      return $cached_data;
    }

    $data = parent::getData($placeholders, $query_args);
    if (empty($data) || !is_object($data) || !property_exists($data, 'results')) {
      return [];
    }
    return $data;
  }

  /**
   * Get the number of projects in the context of the given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   *
   * @return int
   *   The number of projects.
   */
  public function getProjectCount(ContentEntityInterface $context_node = NULL) {
    $projects = $this->getProjects($context_node);
    return count($projects);
  }

  /**
   * Get the number of organizations in the context of the given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   *
   * @return int
   *   The number of organizations.
   */
  public function getOrganizationCount(ContentEntityInterface $context_node = NULL) {
    $organizations = $this->getOrganizations($context_node);
    return count($organizations);
  }

  /**
   * Get the projects for an organization.
   *
   * @param object $organization
   *   The organization for which to look up the projects.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects for the given organization.
   */
  public function getOrganizationProjects($organization, ContentEntityInterface $context_node = NULL) {
    $projects = $this->getProjects($context_node);
    $organization_projects = [];
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if (empty($project->organizations)) {
        continue;
      }
      $organization_ids = array_keys($project->organizations);
      if (in_array($organization->id, $organization_ids)) {
        $organization_projects[$project->id] = $project;
      }
    }
    return $organization_projects;
  }

  /**
   * Get the clusters for an organization.
   *
   * @param object $organization
   *   The organization for which to look up the projects.
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of cluster objects for the given organization.
   */
  public function getOrganizationClusters($organization, ContentEntityInterface $context_node = NULL) {
    $projects = $this->getProjects($context_node);
    $organization_clusters = [];
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if (empty($project->clusters)) {
        continue;
      }
      $organization_ids = array_keys($project->organizations);
      if (in_array($organization->id, $organization_ids)) {
        $organization_clusters += $project->clusters;
      }
    }
    return $organization_clusters;
  }

  /**
   * Get the organizations in the context of the given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An optonal array of projects from which the organizations should be
   *   extracted.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getOrganizations(ContentEntityInterface $context_node = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($context_node);
    }
    $organizations = [];
    if (empty($projects) || !is_array($projects)) {
      return $organizations;
    }
    foreach ($projects as $project) {
      if (!$project->published) {
        continue;
      }
      if (empty($project->organizations)) {
        continue;
      }
      foreach ($project->organizations as $organization) {
        if (!empty($organizations[$organization->id])) {
          continue;
        }
        $organizations[$organization->id] = $organization;

      }
    }
    return $organizations;
  }

  /**
   * Get the projects in the context of the given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param bool $filter_unpublished
   *   Whether unpublished projects should be filtered.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjects(ContentEntityInterface $context_node = NULL, $filter_unpublished = FALSE) {
    $data = $this->getData();
    if (empty($data) || !is_object($data)) {
      return [];
    }

    $projects = [];
    foreach ($data->results as $project) {
      $projects[] = new Project($project);
    }

    if (!empty($context_node) && $context_node->bundle() == 'governing_entity') {
      $context_original_id = $context_node->field_original_id->value;
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

    // Filter out unpublished projects.
    if ($filter_unpublished) {
      $projects = array_filter($projects, function ($project) {
        return !empty($project->published);
      });
    }

    return $projects;
  }

  /**
   * Get the clusters grouped by organizations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param array $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the cluster id and the value is the cluster object as retrieved from
   *   the API.
   */
  public function getClustersByOrganization(ContentEntityInterface $context_node = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($context_node);
    }
    $clusters = [];
    foreach ($projects as $project) {
      if (empty($project->organizations)) {
        continue;
      }
      foreach ($project->organizations as $organization) {
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
    return $clusters;
  }

  /**
   * Get the projects grouped by organizations.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  public function getProjectsByOrganization(ContentEntityInterface $context_node = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($context_node);
    }
    $organization_projects = [];
    foreach ($projects as $project) {
      if (empty($project->organizations)) {
        continue;
      }
      foreach ($project->organizations as $organization) {
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
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param array $projects
   *   An optonal array of projects from which the clusters will be extracted.
   *
   * @return array[]
   *   An array of arrays. First level key is the location id, the value is an
   *   array of project ids associated with that location.
   */
  public function getProjectsByLocation(ContentEntityInterface $context_node = NULL, array $projects = NULL) {
    if (empty($projects)) {
      $projects = $this->getProjects($context_node);
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
