<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\ghi_plans\Entity\GoverningEntity;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction class for API project objects.
 */
class Project extends BaseObject {

  use SimpleCacheTrait;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_DIMENSION_ITEMS = [
    'Id',
    'Name',
    'ProjectCode',
    'Description',
    'StartDate',
    'EndDate',
    'Objective',
    'VisibilityGroupId',
    'ImplementingPartners',
    'ImplementationStatus',
    'CurrentRequestedFunds',
    'RecordStatus',
    'ActiveUntil',
    'Source',
    'SourceId',
    'PlanId',
    'CreatedAt',
    'UpdatedAt',
    'IsLocked',
    'PgSqlPdf',
    'HpcId',
    'HpcVersionId',
  ];

  /**
   * The clusters.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]|null
   */
  private ?array $clusters = NULL;

  /**
   * Map the raw data.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();

    return (object) [
      'id' => $data->Id,
      'name' => $data->Name,
      'code' => $data->ProjectCode,
      'plan_id' => $data->PlanId ?? NULL,
      'sectors' => $data->sectors ?? [],
      'published' => !empty($data->currentPublishedVersionId),
      'requirements' => $data->CurrentRequestedFunds,
      'location_ids' => $data->locationIds->ids ?? [],
      'organizations' => $data->organizations ?? [],
      'target' => !empty($data->targets) ? array_sum(array_map(function ($item) {
        return $item->total;
      }, $data->targets)) : 0,
    ];
  }

  /**
   * Get the plan id.
   *
   * @return int|null
   *   The plan id.
   */
  public function getPlanId(): ?int {
    return $this->map->plan_id !== NULL ? (int) $this->map->plan_id : NULL;
  }

  /**
   * Get the project code.
   *
   * @return string
   *   The project code.
   */
  public function getProjectCode(): string {
    return (string) $this->map->code;
  }

  /**
   * Get the requirements.
   *
   * @return float
   *   The project requirements.
   */
  public function getRequirements(): float {
    return (float) $this->map->requirements;
  }

  /**
   * Process organization objects from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of processed organization objects.
   */
  public function getOrganizations() {
    return $this->map->organizations;
  }

  /**
   * Get the project sectors.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\Sector[]
   *   An array of sectors for this project.
   */
  public function getSectors() {
    return $this->sectors ?? [];
  }

  /**
   * Get the project cluster ids.
   *
   * @return int[]
   *   An array of cluster ids for this project.
   */
  public function getSectorIds() {
    return array_keys($this->getSectors() ?? []);
  }

  /**
   * Get the project clusters.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of clusters for this project.
   */
  public function getClusters() {
    if (!$this->getPlanId()) {
      return [];
    }
    if (is_array($this->clusters)) {
      return $this->clusters;
    }
    /** @var \Drupal\ghi_plans\Entity\GoverningEntity[] $governing_entities */
    $governing_entities = \Drupal::entityTypeManager()->getStorage('base_object')->loadByProperties([
      'type' => GoverningEntity::BUNDLE,
      'field_plan.entity:base_object.field_original_id.value' => $this->getPlanId(),
    ]);
    $sector_ids = $this->getSectorIds();
    $relevant_entities = array_filter($governing_entities, fn ($entity) => in_array($entity->getSectorId(), $sector_ids));
    $this->clusters = array_map(fn ($entity) => new PlanProjectCluster((object) [
      'Id' => $entity->getSourceId(),
      'Name' => $entity->label(),
      // @todo Retrieve icon.
      'Icon' => NULL,
    ]), $relevant_entities);
    return $this->clusters;
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
