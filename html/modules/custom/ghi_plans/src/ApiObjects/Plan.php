<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\Entity\Plan as EntityPlan;
use Drupal\ghi_plans\Traits\PlanQueryTrait;
use Drupal\hpc_api\ApiObjects\Types\PlanCostingType;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_api\Traits\DateTimeTrait;

/**
 * Abstraction class for API plan objects.
 */
class Plan extends BaseObject implements PlanEntityInterface {

  use DateTimeTrait;
  use PlanQueryTrait;

  /**
   * The year.
   *
   * @var int|null
   */
  protected ?int $year;

  /**
   * The short name.
   *
   * @var string|null
   */
  protected ?string $shortName;

  /**
   * The subtitle.
   *
   * @var string|null
   */
  protected ?string $subtitle;

  /**
   * The comments.
   *
   * @var string|null
   */
  protected ?string $comments;

  /**
   * The plan type.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\PlanType|null
   */
  protected ?PlanType $planType;

  /**
   * The plan cluster type.
   *
   * @var string|null
   */
  protected ?string $planClusterType;

  /**
   * The plan costing type.
   *
   * @var \Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   */
  protected ?PlanCostingType $planCostingType;

  /**
   * The reporting periods.
   *
   * @var array
   */
  protected array $reportingPeriods;

  /**
   * The start date.
   *
   * @var string|null
   */
  protected ?string $startDate;

  /**
   * The end date.
   *
   * @var string|null
   */
  protected ?string $endDate;

  /**
   * The created date.
   *
   * @var string|null
   */
  protected ?string $createdDate;

  /**
   * The updated date.
   *
   * @var string|null
   */
  protected ?string $updatedDate;

  /**
   * The document published date.
   *
   * @var string|null
   */
  protected ?string $documentPublishedDate;

  /**
   * The last published period.
   *
   * @var int|null
   */
  protected ?int $lastPublishedPeriod;

  /**
   * The last published period.
   *
   * @var bool
   */
  protected bool $isCurrentVersion;

  /**
   * Whether the plan is released.
   *
   * @var bool
   */
  protected bool $isReleased;

  /**
   * Whether the plan is restricted.
   *
   * @var bool
   */
  protected bool $isRestricted;

  /**
   * Whether the plan is part of GHO.
   *
   * @var bool
   */
  protected bool $isPartOfGho;

  /**
   * The langcode.
   *
   * @var string|null
   */
  protected ?string $langcode;

  /**
   * The countries.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Country[]
   */
  protected array $countries;

  /**
   * The organizations.
   *
   * @var array
   */
  protected array $organizations;

  /**
   * The focus country.
   *
   * @var \Drupal\ghi_base_objects\ApiObjects\Country|null
   */
  protected ?Country $focusCountry;

  const ENTITY_REF_CODE = 'PL';

  /**
   * Define the dimension items used in queries.
   */
  const GRAPHQL_ITEMS = [
    'Id',
    'Name',
    'ShortName',
    'PlanSubTitle',
    'PlanType',
    'PlanCosting',
    'PlanLanguageCode',
    'PlanClusterType',
    'StartDate',
    'EndDate',
    'CreatedAt',
    'UpdatedAt',
    'IsReleased',
    'IsRestricted',
    'IsPartOfGHO',
    'DocumentPublishDate',
    'Description',
    'FocusedLocationName',
    'FocusedLocationId',
    'CurrentReportingPeriodId',
    'LastPublishedReportingPeriodId',
    'IsLegacyCurrentVersion',
    // phpcs:disable Squiz.Arrays.ArrayDeclaration.KeySpecified
    'period' => ['items' => ['CalendarYear']],
    'location' => [
      'filter' => ['AdminLevel' => 0],
      'items' => Country::GRAPHQL_ITEMS,
    ],
    'organization' => [
      'items' => Organization::GRAPHQL_ITEMS,
    ],
    // phpcs:enable Squiz.Arrays.ArrayDeclaration.KeySpecified
  ];

  /**
   * {@inheritdoc}
   */
  public function __construct($data) {
    parent::__construct($data);
    $plan_query = $this->getPlanQuery();

    $this->year = $data->period?->items[0]?->CalendarYear ?? NULL;
    $this->shortName = $data->ShortName ?? NULL;
    $this->subtitle = $data->PlanSubTitle ?? NULL;
    $this->comments = $data->Description ?? NULL;
    $this->planType = ($data->PlanType ?? NULL) ? $plan_query->getPlanTypeByName($data->PlanType) : NULL;
    $this->planClusterType = $data->PlanClusterType ?? NULL;
    $this->planCostingType = ($data->PlanCosting ?? NULL) ? $plan_query->getPlanCostingTypeByName($data->PlanCosting) : NULL;
    $this->reportingPeriods = $data->planReportingPeriods ?? [];
    $this->startDate = ($data->StartDate ?? NULL) ? self::reformatDate($data->StartDate) : NULL;
    $this->endDate = ($data->EndDate ?? NULL) ? self::reformatDate($data->EndDate) : NULL;
    $this->createdDate = ($data->CreatedAt ?? NULL) ? self::getTimestamp($data->CreatedAt) : NULL;
    $this->updatedDate = ($data->UpdatedAt ?? NULL) ? self::getTimestamp($data->UpdatedAt) : NULL;
    $this->documentPublishedDate = ($data->DocumentPublishDate ?? NULL) ? self::reformatDate($data->DocumentPublishDate) : NULL;
    $this->lastPublishedPeriod = $data->LastPublishedReportingPeriodId ?? NULL;
    $this->isCurrentVersion = !empty($data->IsLegacyCurrentVersion);
    $this->isReleased = $data->IsReleased ?? FALSE;
    $this->isRestricted = $data->IsRestricted ?? FALSE;
    $this->isPartOfGho = $data->IsPartOfGHO ?? FALSE;
    $this->langcode = $data->PlanLanguageCode ?? 'en';
    $this->countries = array_map(fn ($item) => new Country($item), $data->location?->items ?? []);
    $this->organizations = array_map(fn ($item) => new Organization($item), $data->organization?->items ?? []);
    $this->focusCountry = ($data->FocusedLocationName ?? NULL) ? $plan_query->lookupCountry($data->FocusedLocationName) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBundle() {
    return 'plan';
  }

  /**
   * Get the base object entity corresponding to this API object.
   *
   * @return \Drupal\ghi_plans\Entity\Plan
   *   The plan entity.
   */
  public function getEntity() {
    $entity = parent::getEntity();
    return $entity instanceof EntityPlan ? $entity : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTypeName() {
    return $this->t('Plan');
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeRefCode() {
    return self::ENTITY_REF_CODE;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityType() {
    return lcfirst((new \ReflectionClass($this))->getShortName());
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeName() {
    $pieces = preg_split('/(?=[A-Z])/', $this->getEntityType());
    return ucfirst(strtolower(implode(' ', $pieces)));
  }

  /**
   * {@inheritdoc}
   */
  public function getYear(): ?int {
    return $this->year;
  }

  /**
   * {@inheritdoc}
   */
  public function getShortName(): string {
    return $this->shortName ?? parent::getShortName();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtitle(): ?string {
    return $this->subtitle ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function getComments(): ?string {
    return $this->comments;
  }

  /**
   * {@inheritdoc}
   */
  public function getCustomName($type): ?string {
    return $this->getPlanTypeAbbreviation();
  }

  /**
   * Get the plan type of the plan.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanType|null
   *   The plan type or NULL.
   */
  public function getPlanType(): ?PlanType {
    return $this->planType ?? NULL;
  }

  /**
   * Get the abbreviation of the plan type.
   */
  public function getPlanTypeAbbreviation(): ?string {
    if ($plan_type = $this->getEntity()?->getPlanType()) {
      // Prefer to get the abbreviation from the term entity as that can be
      // overridden.
      return $plan_type->getAbbreviation();
    }
    // Otherwise get it from the API object.
    return $this->planType?->getAbbreviation() ?? NULL;
  }

  /**
   * Get the plan costing type of the plan.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   *   The plan costing type or NULL.
   */
  public function getPlanCostingType(): ?PlanCostingType {
    return $this->planCostingType ?? NULL;
  }

  /**
   * Get the plan cluster type of the plan.
   *
   * @return string|null
   *   The plan cluster type or NULL.
   */
  public function getPlanClusterType(): ?string {
    return $this->planClusterType ?? NULL;
  }

  /**
   * Get the start date of the plan.
   *
   * @return string|null
   *   The start date as a string.
   */
  public function getStartDate(): ?string {
    return $this->startDate;
  }

  /**
   * Get the end date of the plan.
   *
   * @return string|null
   *   The end date as a string.
   */
  public function getEndDate(): ?string {
    return $this->endDate;
  }

  /**
   * Get the created date of the plan.
   *
   * @return string|null
   *   The created date as a string.
   */
  public function getCreatedDate(): ?string {
    return $this->createdDate;
  }

  /**
   * Get the updated date of the plan.
   *
   * @return string|null
   *   The updated date as a string.
   */
  public function getUpdatedDate(): ?string {
    return $this->updatedDate;
  }

  /**
   * Get the document published date of the plan.
   *
   * @return string|null
   *   The document published date as a string.
   */
  public function getDocumentPublishedDate(): ?string {
    return $this->documentPublishedDate;
  }

  /**
   * Get the language code of the plan.
   *
   * @return string
   *   The language code as a boolean.
   */
  public function getLanguageCode(): string {
    return $this->langcode;
  }

  /**
   * Get the latest published reporting period.
   *
   * @return int|null
   *   The last published reporting period.
   */
  public function getLastPublishedReportingPeriodId(): ?int {
    return $this->lastPublishedPeriod ?? NULL;
  }

  /**
   * Get the plan countries.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country[]
   *   An array of country objects.
   */
  public function getCountries(): array {
    return $this->countries;
  }

  /**
   * Get the plan organizations.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization[]
   *   An array of organization objects.
   */
  public function getPlanOrganizations(): array {
    return $this->organizations;
  }

  /**
   * Get the focus country.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   An array of country objects.
   */
  public function getFocusCountry(): ?Country {
    return $this->focusCountry;
  }

  /**
   * Get the released state of the plan.
   *
   * @return bool
   *   The released state as a boolean.
   */
  public function isReleased(): bool {
    return $this->isReleased;
  }

  /**
   * Get the restricted state of the plan.
   *
   * @return bool
   *   The restricted state as a boolean.
   */
  public function isRestricted(): bool {
    return $this->isRestricted;
  }

  /**
   * Get the GHO state of the plan.
   *
   * @return bool
   *   The GHO state as a boolean.
   */
  public function isPartOfGho(): bool {
    return $this->isPartOfGho;
  }

}
