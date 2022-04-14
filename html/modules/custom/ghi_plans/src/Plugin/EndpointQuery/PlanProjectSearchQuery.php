<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_common\Helpers\CommonHelper;

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
    $cache_key = $this->getCacheKeyFromAssociativeArray($placeholders);
    if ($cached_data = $this->cache($cache_key)) {
      return $cached_data;
    }

    $data = parent::getData($placeholders, $query_args);
    if (empty($data) || !is_object($data) || !property_exists($data, 'results')) {
      return [];
    }
    $projects = [];
    foreach ($data->results as $project) {
      // Extract the cluster ids.
      $cluster_ids = property_exists($project, 'clusterId') ? [$project->clusterId] : [];
      if (empty($cluster_ids) && property_exists($project, 'governingEntities')) {
        $cluster_ids = array_map(function ($governing_entity) {
          return $governing_entity->id;
        }, $project->governingEntities);
      }

      $projects[] = (object) [
        'id' => $project->id,
        'name' => $project->name,
        'version_code' => $project->versionCode,
        'cluster_ids' => $cluster_ids,
        'organizations' => $this->processProjectOrganizations($project),
        'published' => $project->currentPublishedVersionId,
        'requirements' => $project->currentRequestedFunds,
        'target' => !empty($project->targets) ? array_sum(array_map(function ($item) {
          return $item->total;
        }, $project->targets)) : 0,
      ];
    }
    return $projects;
  }

  /**
   * Process organization objects from the API.
   *
   * @param object $project
   *   A project object as returned by the API.
   *
   * @return array
   *   An array of processed organization objects.
   */
  private function processProjectOrganizations($project) {
    $processed_organizations = [];

    // First find the organizations. There are 2 ways.
    $project_organizations = !empty($project->organizations) ? $project->organizations : [];
    if (property_exists($project, 'projectVersions')) {
      $project_version = array_filter($project->projectVersions, function ($item) use ($project) {
        return $item->id == $project->currentPublishedVersionId && !empty($item->organizations);
      });
      $project_organizations = $project_version->organizations;
    }

    // Now process the organizations.
    foreach ($project_organizations as $organization) {
      if (!empty($processed_organizations[$organization->id])) {
        continue;
      }
      $processed_organizations[$organization->id] = (object) [
        'id' => $organization->id,
        'name' => $organization->name,
        'url' => CommonHelper::assureWellFormedUri($organization->url),
      ];
    }
    return $processed_organizations;
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
   * @return object[]
   *   An array of project objects for the given organization.
   */
  public function getOrganizationProjects($organization, ContentEntityInterface $context_node = NULL) {
    $projects = $this->getProjects($context_node);
    $organization_projects = [];
    foreach ($projects as $project) {
      if (empty($project->organizations)) {
        continue;
      }
      $organization_ids = array_map(function ($_organization) {
        return $_organization->id;
      }, $project->organizations);

      if (in_array($organization->id, $organization_ids)) {
        $organization_projects[] = $project;
      }
    }
    return $organization_projects;
  }

  /**
   * Get the organizations in the context of the given node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $context_node
   *   The context node.
   * @param array $projects
   *   An optonal array of projects from which the organizations should be
   *   extracted.
   *
   * @return array
   *   An array of organization objects as returned from the API.
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
   * @return array
   *   An array of projects.
   */
  public function getProjects(ContentEntityInterface $context_node = NULL, $filter_unpublished = FALSE) {
    $data = $this->getData();
    if (empty($data) || !is_array($data)) {
      return [];
    }

    if (!empty($context_node) && $context_node->bundle() == 'governing_entity') {
      $context_original_id = $context_node->field_original_id->value;
      $projects = array_filter($data, function ($item) use ($context_original_id) {
        return in_array($context_original_id, $item->cluster_ids);
      });
    }
    else {
      $projects = $data;
    }

    if ($this->filterByClusterIds !== NULL) {
      $cluster_ids = $this->filterByClusterIds;
      $projects = array_filter($data, function ($item) use ($cluster_ids) {
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

}
