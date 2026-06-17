<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\ghi_plans\Traits\ProjectTrait;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_common\Helpers\ArrayHelper;

/**
 * Plugin implementation of the 'project' fabric query.
 *
 * @todo Add filters to only load accepted projects.
 */
#[FabricQuery(
  id: 'project',
  label: new TranslatableMarkup('Project query'),
)]
class ProjectQuery extends FabricQueryBase {

  use PlanQueryTrait;
  use ProjectTrait;

  /**
   * Get the base data for an project.
   *
   * @param int $project_id
   *   The project id.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_context
   *   An optional plan to associate the project to.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project|null
   *   An project object or NULL.
   */
  public function getProject(int $project_id, ?Plan $plan_context = NULL): ?Project {
    $projects = $this->getProjectsById([$project_id], $plan_context);
    return !empty($projects) ? reset($projects) : NULL;
  }

  /**
   * Get the base data for an project.
   *
   * @param int[] $project_ids
   *   The project ids.
   * @param \Drupal\ghi_plans\Entity\Plan|null $plan_context
   *   An optional plan to associate the projects to.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjectsById(array $project_ids, ?Plan $plan_context = NULL): array {
    if (empty($project_ids)) {
      return [];
    }
    if (count($project_ids) > self::MAX_FILTER_COUNT_ARRAY) {
      $projects = $this->doChunkedQuery($project_ids, fn ($ids): array => $this->getProjectsById($ids, $plan_context));
      $this->sortProjectsByProjectCode($projects);
      return $projects;
    }
    $items = $this->fabricClient->createQuery('projects', Project::getGraphQlItems())
      ->setFilter('Id', $project_ids)
      ->setFilter('IsPublished', TRUE)
      ->setOrderBy(['ProjectCode' => 'ASC'])
      ->execute() ?: [];

    if ($plan_context) {
      array_walk($items, fn (&$item) => $item->PlanId = $plan_context->getSourceId());
    }

    // Fetch organizations, clusters and location ids.
    $this->addOrganizationsToProjectItems($items);
    $this->addFieldClustersToProjectItems($items);
    $this->addLocationIdsToProjectItems($items);
    $projects = $this->buildResultObjects($items, Project::class);
    $this->sortProjectsByProjectCode($projects);
    return $projects;
  }

  /**
   * Get the number of projects in the context of the given node.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface $context_base_object
   *   The context base object.
   *
   * @return int
   *   The number of projects.
   */
  public function getProjectCountForPlan(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL) {
    return count($this->getProjectsForPlanId($plan->getSourceId(), $context_base_object));
  }

  /**
   * Get the total project requirements for the given plan id.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return float
   *   The total requirement sum of the projects.
   */
  public function getProjectRequirementsForPlan(int $plan_id) {
    $aggregations = $this->fabricClient->createQuery('projects')
      ->setFilters([
        'PlanId' => $plan_id,
        'IsPublished' => TRUE,
      ])
      ->setAggregation('Id', [
        'sum' => 'CurrentRequestedFunds',
      ])
      ->execute() ?: NULL;
    return (float) $aggregations?->sum ?? 0;
  }

  /**
   * Get all projects for the given plan.
   *
   * @param int $plan_id
   *   The plan id.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   * @param int|null $organization_id
   *   An optional organization id, to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjectsForPlanId(int $plan_id, ?BaseObjectChildInterface $context_base_object = NULL, ?int $organization_id = NULL): array {
    $items = $this->fabricClient->createQuery('projects', Project::getGraphQlItems())
      ->setFilter('PlanId', $plan_id)
      ->setFilter('IsPublished', TRUE)
      ->setOrderBy(['ProjectCode' => 'ASC'])
      ->execute() ?: [];

    // Fetch organizations, clusters and location ids.
    $this->addOrganizationsToProjectItems($items);
    $this->addFieldClustersToProjectItems($items);
    $this->addLocationIdsToProjectItems($items);
    /** @var \Drupal\ghi_plans\ApiObjects\Project[] $projects */
    $projects = $this->buildResultObjects($items, Project::class);

    if ($context_base_object instanceof GoverningEntity) {
      $projects = array_filter($projects, fn ($project) => in_array($context_base_object->getSourceId(), $project->getClusterIds()));
    }

    if ($organization_id !== NULL) {
      $projects = array_filter($projects, fn ($project) => array_key_exists($organization_id, $project->getOrganizations()));
    }

    $this->sortProjectsByProjectCode($projects);
    return $projects;
  }

  /**
   * Sort projects by their project code.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Project[] $projects
   *   An array of project objects.
   */
  private function sortProjectsByProjectCode(array &$projects): void {
    ArrayHelper::sortObjectsByMethod($projects, 'getProjectCode', SORT_ASC, SORT_STRING);
  }

  /**
   * Get all organizations referenced by the plans projects.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   * @param int|null $organization_id
   *   An optional organization id, to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getProjectOrganizationsForPlan(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL, ?int $organization_id = NULL): array {
    $projects = $this->getProjectsForPlanId($plan->getSourceId(), $context_base_object, $organization_id);
    $organizations = [];
    foreach ($projects as $project) {
      $organizations += $project->getOrganizations();
    }
    ArrayHelper::sortObjectsByMethod($organizations, 'getName', SORT_ASC, SORT_STRING);
    return $organizations;
  }

  /**
   * Get all clusters referenced by the plans projects.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   * @param int|null $organization_id
   *   An optional organization id, to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getProjectClustersForPlan(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL, ?int $organization_id = NULL): array {
    $projects = $this->getProjectsForPlanId($plan->getSourceId(), $context_base_object, $organization_id);
    $clusters = [];
    foreach ($projects as $project) {
      $clusters += $project->getClusters();
    }
    ArrayHelper::sortObjectsByMethod($clusters, 'getName', SORT_ASC, SORT_STRING);
    return $clusters;
  }

  /**
   * Get the projects grouped by organizations.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the project id and the value is a project object.
   */
  public function getPlanProjectsByOrganization(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL): array {
    $projects = $this->getProjectsForPlanId($plan->getSourceId(), $context_base_object);
    $organization_projects = $this->groupProjectsByOrganization($projects);
    return $organization_projects;
  }

  /**
   * Get the clusters grouped by organizations.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   *
   * @return array[]
   *   An array of arrays. First level key is the organization id, second level
   *   key the cluster id and the value is a plan cluster object.
   */
  public function getProjectClustersByOrganization(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL): array {
    $projects = $this->getProjectsForPlanId($plan->getSourceId(), $context_base_object);
    $clusters = [];
    foreach ($projects as $project) {
      $project_organizations = $project->getOrganizations();
      if (empty($project_organizations)) {
        continue;
      }
      foreach ($project_organizations as $organization) {
        $clusters[$organization->id()] = $clusters[$organization->id()] ?? [];
        foreach ($project->getClusters() as $cluster) {
          if (!empty($clusters[$organization->id()][$cluster->id()])) {
            continue;
          }
          $clusters[$organization->id()][$cluster->id()] = $cluster;
        }
      }
    }
    return $clusters;
  }

  /**
   * Add organizations to the given project items.
   *
   * @param array $items
   *   An array of fabric result items.
   */
  private function addOrganizationsToProjectItems(array &$items): void {
    if (empty($items)) {
      return;
    }
    foreach ($items as &$item) {
      $organizations = array_map(fn ($reference): Organization => new Organization($reference), $item->organization->items);
      $organization_ids = $this->extractIds($organizations);
      $item->organizations = array_combine($organization_ids, $organizations);
    }
  }

  /**
   * Add clusters to the given project items.
   *
   * @param array $items
   *   An array of fabric result items.
   */
  private function addFieldClustersToProjectItems(array &$items): void {
    if (empty($items)) {
      return;
    }
    foreach ($items as &$item) {
      $clusters = array_map(fn ($reference): PlanProjectCluster => new PlanProjectCluster($reference), $item->coordinationEntity->items);
      $cluster_ids = $this->extractIds($clusters);
      $item->clusters = array_combine($cluster_ids, $clusters);
    }
  }

  /**
   * Add location ids to the given project items.
   *
   * @param array $items
   *   An array of fabric result items.
   */
  private function addLocationIdsToProjectItems(array &$items): void {
    if (empty($items)) {
      return;
    }
    foreach ($items as &$item) {
      $location_ids = array_map(fn ($reference) => $reference->Id, $item->location->items);
      $item->locationIds = $location_ids;
    }
  }

}
