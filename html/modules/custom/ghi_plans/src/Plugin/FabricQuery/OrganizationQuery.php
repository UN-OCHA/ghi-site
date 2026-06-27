<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\Entity\BaseObjectChildInterface;
use Drupal\ghi_base_objects\Entity\BaseObjectInterface;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\ApiObjects\Project;
use Drupal\ghi_plans\Entity\Plan;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Plugin implementation of the 'organization' fabric query.
 */
#[FabricQuery(
  id: 'organization',
  label: new TranslatableMarkup('Organization query'),
)]
class OrganizationQuery extends FabricQueryBase {

  use SimpleCacheTrait;

  /**
   * Get the base data for an organization.
   *
   * @param int $organization_id
   *   The organization id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization|null
   *   An organization object or NULL.
   */
  public function getOrganization(int $organization_id): ?Organization {
    $organizations = $this->getOrganizationsById([$organization_id]);
    return !empty($organizations) ? reset($organizations) : NULL;
  }

  /**
   * Get the base data for a list of organizations.
   *
   * @param int[] $organization_ids
   *   The organization ids.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getOrganizationsById(array $organization_ids): array {
    if (empty($organization_ids)) {
      return [];
    }
    $organization_ids = array_unique($organization_ids);
    sort($organization_ids);
    $items = $this->fabricClient->createQuery('organizations', Organization::getGraphQlItems())
      ->setFilter('Id', $organization_ids)
      ->execute() ?: [];
    $organizations = $this->buildResultObjects($items, Organization::class);
    return $organizations;
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
  public function getProjectsForOrganization(Organization $organization, ?BaseObjectInterface $base_object = NULL) {
    $cache_key = $this->getCacheKey(array_filter([
      'organization' => $organization->id(),
      'base_object' => $base_object ? $base_object->bundle() . ':' . $base_object->id() : 'none',
    ]));
    if ($projects = $this->getCache($cache_key)) {
      return $projects;
    }
    $plan_id = $base_object instanceof Plan ? $base_object->getSourceId() : ($base_object instanceof BaseObjectChildInterface ? $base_object->getParentBaseObject()?->getSourceId() : NULL);
    $relationships = $this->getRelationshipItems(NULL, 18, NULL, $organization->id());
    $project_ids = array_map(fn ($item) => $item->getSourceId(), $relationships);
    $items = $this->fabricClient->createQuery('projects', Project::getGraphQlItems())
      ->setFilters(array_filter([
        'Id' => $project_ids,
        'PlanId' => $plan_id,
      ]))
      ->execute() ?: [];
    $projects = $this->buildResultObjects($items, Project::class);
    $this->setCache($cache_key, $projects);
    return $projects;
  }

}
