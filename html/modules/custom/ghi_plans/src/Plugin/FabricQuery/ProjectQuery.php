<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
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
    $items = $this->fabricClient->createQuery('projects', Project::getGraphQlItems())
      ->setFilter('Id', $project_id)
      ->execute() ?: [];
    $items = count($items) == 1 ? [reset($items)] : [];

    if ($plan_context) {
      array_walk($items, fn (&$item) => $item->PlanId = $plan_context->getSourceId());
    }

    // Fetch organizations, clusters and location ids.
    $this->addOrganizationsToProjectItems($items);
    $this->addFieldClustersToProjectItems($items);
    $this->addLocationIdsToProjectItems($items);
    return count($items) == 1 ? new Project($items[0]) : NULL;
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
      return $this->doChunkedQuery($project_ids, fn ($ids): array => $this->getProjectsById($ids, $plan_context));
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
    return $this->buildResultObjects($items, Project::class);
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
    return count($this->getProjectsForPlan($plan, $context_base_object));
  }

  /**
   * Get all projects for the given plan.
   *
   * @param \Drupal\ghi_plans\Entity\Plan $plan
   *   The plan object.
   * @param \Drupal\ghi_base_objects\Entity\BaseObjectChildInterface|null $context_base_object
   *   An optional context object, should be a cluster if given.
   * @param int|null $organization_id
   *   An optional organization id, to filter for.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Project[]
   *   An array of project objects.
   */
  public function getProjectsForPlan(Plan $plan, ?BaseObjectChildInterface $context_base_object = NULL, ?int $organization_id = NULL): array {
    $project_type = $this->getEntityTypeByName('Project');
    $plan_type = $this->getEntityTypeByName('Plan');
    $relationships = $this->getRelationshipItems($project_type->id(), $plan_type->id(), NULL, $plan->getSourceId());
    $project_ids = array_map(fn ($item) => $item->getSourceId(), $relationships);
    $projects = !empty($project_ids) ? $this->getProjectsById($project_ids, $plan) : [];
    if ($context_base_object instanceof GoverningEntity) {
      $projects = array_filter($projects, fn ($project) => in_array($context_base_object->getSourceId(), $project->getClusterIds()));
    }
    if ($organization_id !== NULL) {
      $projects = array_filter($projects, fn ($project) => array_key_exists($organization_id, $project->getOrganizations()));
    }
    return $projects;
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
    $projects = $this->getProjectsForPlan($plan, $context_base_object, $organization_id);
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
    $projects = $this->getProjectsForPlan($plan, $context_base_object, $organization_id);
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
    $projects = $this->getProjectsForPlan($plan, $context_base_object);
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
    $projects = $this->getProjectsForPlan($plan, $context_base_object);
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
    if (count($items) > self::MAX_FILTER_COUNT_ARRAY) {
      // We need to do multiple queries.
      for ($i = 0; $i < ceil(count($items) / self::MAX_FILTER_COUNT_ARRAY); $i++) {
        $subset = array_slice($items, $i * self::MAX_FILTER_COUNT_ARRAY, self::MAX_FILTER_COUNT_ARRAY);
        $this->addOrganizationsToProjectItems($subset);
      }
      return;
    }
    $project_ids = array_keys($items);

    $project_type = $this->getEntityTypeByName('Project');
    $organization_type = $this->getEntityTypeByName('Organization');
    $relationships = $this->getRelationshipItems($project_type->id(), $organization_type->id(), $project_ids);

    $organization_ids = array_unique(array_map(fn ($item) => $item->getTargetId(), $relationships));
    sort($organization_ids);

    $organizations = $this->getOrganizationQuery()->getOrganizationsById($organization_ids);

    foreach ($relationships as $item) {
      $project_id = $item->getSourceId();
      $organization_id = $item->getTargetId();
      $items[$project_id]->organizations = $items[$project_id]->organizations ?? [];
      $items[$project_id]->organizations[$organization_id] = $organizations[$organization_id];
    }
  }

  /**
   * Add clusters to the given project items.
   *
   * @param array $projects
   *   An array of fabric result items.
   */
  private function addFieldClustersToProjectItems(array &$projects): void {
    if (empty($projects)) {
      return;
    }
    $project_ids = array_keys($projects);
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    $items = $this->fabricClient->createQuery('projectFieldClusters', [
      'Id',
      'ProjectId',
      'coordinationEntity' => PlanProjectCluster::getGraphQlItems(),
    ])
      ->setFilter('ProjectId', $project_ids)
      ->setOrderBy(['ProjectId' => 'ASC'])
      ->execute() ?: [];
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
    foreach ($items as $item) {
      $project_id = $item->ProjectId;
      $projects[$project_id]->clusters = $projects[$project_id]->clusters ?? [];
      $projects[$project_id]->clusters[$item->coordinationEntity->Id] = new PlanProjectCluster($item->coordinationEntity);
    }
  }

  /**
   * Add location ids to the given project items.
   *
   * @param array $projects
   *   An array of fabric result items.
   */
  private function addLocationIdsToProjectItems(array &$projects): void {
    if (empty($projects)) {
      return;
    }
    $project_ids = array_keys($projects);
    $items = $this->fabricClient->createQuery('projectLocations', [
      'Id',
      'ProjectId',
      'LocationId',
    ])
      ->setFilter('ProjectId', $project_ids)
      ->setOrderBy(['ProjectId' => 'ASC'])
      ->execute() ?: [];
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
    foreach ($items as $item) {
      $project_id = $item->ProjectId;
      $projects[$project_id]->locationIds = $projects[$project_id]->locationIds ?? [];
      $projects[$project_id]->locationIds[] = $item->LocationId;
    }
  }

}
