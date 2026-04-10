<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster;
use Drupal\hpc_api\ApiObjects\ApiObjectBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Abstraction class for API project objects.
 */
class Project extends ApiObjectBase {

  use SimpleCacheTrait;

  /**
   * The name.
   *
   * @var string
   */
  protected string $name;

  /**
   * The code.
   *
   * @var string
   */
  protected string $code;

  /**
   * The plan id.
   *
   * @var int|null
   */
  protected ?int $planId;

  /**
   * The clusters.
   *
   * @var \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]|null
   */
  protected array $clusters;

  /**
   * Whether the project is published.
   *
   * @var bool
   */
  protected bool $published;

  /**
   * The requirements.
   *
   * @var float
   */
  protected float $requirements;

  /**
   * The location ids.
   *
   * @var array
   */
  protected array $locationIds;

  /**
   * The organizations.
   *
   * @var array
   */
  protected array $organizations;

  /**
   * The target.
   *
   * @var float
   */
  protected float $target;

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'ProjectCode',
    'Description',
    'StartDate',
    'EndDate',
    'IsPublished',
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
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    'projectFieldCluster' => ['items' => ['coordinationEntity' => PlanProjectCluster::GRAPHQL_ITEMS]],
    'projectOrganization' => ['items' => ['organization' => Organization::GRAPHQL_ITEMS]],
    'projectLocation' => ['items' => ['LocationId']],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    parent::__construct($data);
    $this->name = $data->Name;
    $this->code = (string) $data->ProjectCode;
    $this->planId = $data->PlanId !== NULL ? (int) $data->PlanId : NULL;
    $this->clusters = $data->clusters ?? [];
    $this->published = !empty($data->IsPublished);
    $this->requirements = (float) $data->CurrentRequestedFunds;
    $this->locationIds = $data->locationIds ?? [];
    $this->organizations = $data->organizations ?? [];
    $this->target = !empty($data->targets) ? (float) array_sum(array_map(function ($item) {
      return $item->total;
    }, $data->targets)) : 0;
  }

  /**
   * Get the name.
   *
   * @return string
   *   The name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Get the plan id.
   *
   * @return int|null
   *   The plan id.
   */
  public function getPlanId(): ?int {
    return $this->planId;
  }

  /**
   * Get the project code.
   *
   * @return string
   *   The project code.
   */
  public function getProjectCode(): string {
    return $this->code;
  }

  /**
   * Whether the project is published or not.
   *
   * @return bool
   *   TRUE if the project is published, FALSE otherwise.
   */
  public function isPublished(): bool {
    return $this->published;
  }

  /**
   * Get the target.
   *
   * @return float
   *   The project target.
   */
  public function getTarget(): float {
    return $this->target;
  }

  /**
   * Get the requirements.
   *
   * @return float
   *   The project requirements.
   */
  public function getRequirements(): float {
    return $this->requirements;
  }

  /**
   * Process organization objects from the API.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of processed organization objects.
   */
  public function getOrganizations(): array {
    return array_filter($this->organizations ?? [], fn ($item) => is_object($item) && $item instanceof Organization);
  }

  /**
   * Get the project clusters.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Partials\PlanProjectCluster[]
   *   An array of clusters for this project.
   */
  public function getClusters(): array {
    return array_filter($this->clusters ?? [], fn ($item) => is_object($item) && $item instanceof PlanProjectCluster);
  }

  /**
   * Get the project cluster ids.
   *
   * @return int[]
   *   An array of cluster ids for this project.
   */
  public function getClusterIds(): array {
    return array_keys($this->getClusters() ?? []);
  }

  /**
   * Get the project location ids.
   *
   * @return int[]
   *   An array of location ids for this project.
   */
  public function getLocationIds(): array {
    return $this->locationIds ?? [];
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
  public function hasOrganization(Organization $organization): bool {
    $organizations = $this->getOrganizations();
    return array_key_exists($organization->id(), $organizations);
  }

}
