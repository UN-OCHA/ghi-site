<?php

namespace Drupal\ghi_plans\Query;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\PageCache\ResponsePolicy\KillSwitch;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use Drupal\hpc_api\Query\EndpointQuery;
use Drupal\hpc_common\Helpers\CommonHelper;
use Drupal\node\NodeInterface;

/**
 * Query class for using the project search API.
 */
class PlanProjectSearchQuery extends EndpointQuery {

  /**
   * A list of cluster ids to be used as filters.
   *
   * @var array
   */
  protected $filterByClusterIds = NULL;

  /**
   * Constructs a new PlanProjectSearchQuery object.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache, KillSwitch $kill_switch, ClientInterface $http_client, AccountProxyInterface $user) {
    parent::__construct($config_factory, $logger_factory, $cache, $kill_switch, $http_client, $user);

    $this->endpointUrl = 'public/project/search';
    $this->endpointVersion = 'v2';
    $this->endpointArgs = [
      'planIds' => '{plan_id}',
      'latest' => 'true',
      'excludeFields' => 'plans,workflowStatusOptions,locations',
      'includeFields' => 'locationIds,planEntityIds',
      'limit' => 1000,
    ];
  }

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
  public function getData() {
    $data = parent::getData();

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
   * @param \Drupal\node\NodeInterface $context_node
   *   The context node.
   *
   * @return int
   *   The number of projects.
   */
  public function getProjectCount(NodeInterface $context_node = NULL) {
    $projects = $this->getProjects($context_node);
    return count($projects);
  }

  /**
   * Get the number of organizations in the context of the given node.
   *
   * @param \Drupal\node\NodeInterface $context_node
   *   The context node.
   *
   * @return int
   *   The number of organizations.
   */
  public function getOrganizationCount(NodeInterface $context_node = NULL) {
    $organizations = $this->getOrganizations($context_node);
    return count($organizations);
  }

  /**
   * Get the organizations in the context of the given node.
   *
   * @param \Drupal\node\NodeInterface $context_node
   *   The context node.
   * @param array $projects
   *   An optonal array of projects from which the organizations should be
   *   extracted.
   *
   * @return array
   *   An array of organizations.
   */
  public function getOrganizations(NodeInterface $context_node = NULL, array $projects = NULL) {
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
   * @param \Drupal\node\NodeInterface $context_node
   *   The context node.
   * @param bool $filter_unpublished
   *   Whether unpublished projects should be filtered.
   *
   * @return array
   *   An array of projects.
   */
  public function getProjects(NodeInterface $context_node = NULL, $filter_unpublished = FALSE) {
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
