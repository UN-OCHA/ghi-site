<?php

namespace Drupal\ghi_plans\ApiObjects;

use Drupal\ghi_base_objects\ApiObjects\BaseObject;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\ghi_plans\Entity\Plan as EntityPlan;
use Drupal\hpc_api\ApiObjects\Types\PlanCostingType;
use Drupal\hpc_api\ApiObjects\Types\PlanType;
use Drupal\hpc_common\Helpers\StringHelper;

/**
 * Abstraction class for API plan objects.
 */
class Plan extends BaseObject implements PlanEntityInterface {

  const ENTITY_REF_CODE = 'PL';

  const GRAPHQL_ITEMS = "
    Id
    Name
    ShortName
    PlanSubTitle
    PlanType
    PlanCosting
    PlanLanguageCode
    PlanClusterType
    StartDate
    EndDate
    IsReleased
    IsRestricted
    IsPartOfGHO
    DocumentPublishDate
    Description
    period (
      filter: { PeriodType: { eq: \"Year\" } },
      orderBy: { CalendarYear: ASC },
      first: 1
    ) {
      items {
        CalendarYear
      }
    }
    location {
      items {
        Id
        Name
        ISO3
        Latitude
        Longitude
      }
    }
    FocusedLocationName
  ";

  /**
   * Map the raw data.
   *
   * This uses only what we needed up to now. More properties can be mapped if
   * needed.
   *
   * @return object
   *   An object with the mapped data.
   */
  protected function map() {
    $data = $this->getRawData();
    // Make sure we have proper objects for the plan types.
    /** @var \Drupal\hpc_api\ApiObjects\Types\PlanType[] $plan_types */
    $plan_types = array_filter($data->PlanTypes ?? [], fn ($item): bool => $item instanceof PlanType);
    /** @var \Drupal\hpc_api\ApiObjects\Types\PlanClusterType[] $plan_costing_types */
    $plan_costing_types = array_filter($data->PlanCostingTypes ?? [], fn ($item): bool => $item instanceof PlanCostingType);
    return (object) [
      'id' => $data->HpcId,
      'name' => $data->Name,
      'year' => $data->period->items[0]->CalendarYear ?? NULL,
      'short_name' => $data->ShortName ?? NULL,
      'subtitle' => $data->PlanSubTitle ?? NULL,
      'description' => $data->Description ?? NULL,
      'plan_type' => count($plan_types) ? reset($plan_types)->getName() : NULL,
      'plan_cluster_type' => $data->PlanClusterType ?? NULL,
      'plan_costing_type' => count($plan_costing_types) ? reset($plan_costing_types)->getName() : NULL,
      'start_date' => $data->StartDate ? $this->reformatDate($data->StartDate) : NULL,
      'end_date' => $data->EndDate ? $this->reformatDate($data->EndDate) : NULL,
      'document_published_date' => $data->DocumentPublishDate ? $this->reformatDate($data->DocumentPublishDate) : NULL,
      'last_published_period' => NULL,
      'is_released' => $data->IsReleased ?? FALSE,
      'is_restricted' => $data->IsRestricted ?? FALSE,
      'is_part_of_gho' => $data->IsPartOfGHO ?? FALSE,
      'langcode' => $data->PlanLanguageCode ?? 'en',
      'countries' => array_map(fn ($item) => new Country($item), $data->location->items ?? []),
      'focus_country' => $data->FocusCountry,
    ];
  }

  /**
   * Get the graphql query for fetching a plan.
   *
   * @param int $plan_id
   *   The plan id.
   *
   * @return string
   *   The graphql query payload for loading plan data.
   */
  public static function getGraphQlQuery(int $plan_id): string {
    return "
      {
        plans (filter:  {
          HpcId:  {
            eq: {$plan_id}
          }
        }) {
          items { {${self::GRAPHQL_ITEMS} }
        }
      }";
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
  public function getYear() {
    return $this->map->year;
  }

  /**
   * {@inheritdoc}
   */
  public function getShortName(): string {
    return $this->map->short_name ?? parent::getShortName();
  }

  /**
   * {@inheritdoc}
   */
  public function getSubtitle(): ?string {
    return $this->map->subtitle ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->map->description;
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
    return $this->map->plan_type ?? NULL;
  }

  /**
   * Get the abbreviation of the plan type.
   */
  public function getPlanTypeAbbreviation(): ?string {
    if ($plan_type = $this->getEntity()?->getPlanType()) {
      return $plan_type->getAbbreviation();
    }
    return $this->plan_type ? StringHelper::getAbbreviation($this->plan_type) : NULL;
  }

  /**
   * Get the plan costing type of the plan.
   *
   * @return \Drupal\hpc_api\ApiObjects\Types\PlanCostingType|null
   *   The plan costing type or NULL.
   */
  public function getPlanCostingType(): ?PlanCostingType {
    return $this->map->plan_costing_type ?? NULL;
  }

  /**
   * Get the plan cluster type of the plan.
   *
   * @return string|null
   *   The plan cluster type or NULL.
   */
  public function getPlanClusterType(): ?string {
    return $this->map->plan_cluster_type ?? NULL;
  }

  /**
   * Get the start date of the plan.
   *
   * @return string|null
   *   The start date as a string.
   */
  public function getStartDate(): ?string {
    return $this->map->start_date;
  }

  /**
   * Get the end date of the plan.
   *
   * @return string|null
   *   The end date as a string.
   */
  public function getEndDate(): ?string {
    return $this->map->end_date;
  }

  /**
   * Get the document published date of the plan.
   *
   * @return string|null
   *   The document published date as a string.
   */
  public function getDocumentPublishedDate(): ?string {
    return $this->map->document_published_date;
  }

  /**
   * Get the language code of the plan.
   *
   * @return string
   *   The language code as a boolean.
   */
  public function getLanguageCode(): string {
    return $this->map->langcode;
  }

  /**
   * Get the latest published reporting period.
   *
   * @return int
   *   The last published reporting period.
   */
  public function getLastPublishedReportingPeriodId() {
    return $this->last_published_period;
  }

  /**
   * Get the plan countries.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country[]
   *   An array of country objects.
   */
  public function getCountries(): array {
    return $this->map->countries;
  }

  /**
   * Get the focus country.
   *
   * @return \Drupal\ghi_base_objects\ApiObjects\Country|null
   *   An array of country objects.
   */
  public function getFocusCountry(): ?Country {
    return $this->map->focus_country;
  }

  /**
   * Get the released state of the plan.
   *
   * @return bool
   *   The released state as a boolean.
   */
  public function isReleased(): bool {
    return $this->map->is_released;
  }

  /**
   * Get the restricted state of the plan.
   *
   * @return bool
   *   The restricted state as a boolean.
   */
  public function isRestricted(): bool {
    return $this->map->is_restricted;
  }

  /**
   * Get the GHO state of the plan.
   *
   * @return bool
   *   The GHO state as a boolean.
   */
  public function isPartOfGho(): bool {
    return $this->map->is_part_of_gho;
  }

  /**
   * Reformat a date for internal use in the format Y-m-d.
   *
   * @param string $date
   *   The original date string.
   *
   * @return string
   *   The reformatted string.
   */
  private function reformatDate(string $date): string {
    $datetime = new \DateTime($date, new \DateTimeZone('UTC'));
    return $datetime->format('Y-m-d');
  }

}
