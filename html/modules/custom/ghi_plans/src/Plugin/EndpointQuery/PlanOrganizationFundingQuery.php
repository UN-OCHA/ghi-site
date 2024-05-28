<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\hpc_api\Query\EndpointQueryBase;

/**
 * Provides a query plugin for plan funding summary.
 *
 * @EndpointQuery(
 *   id = "plan_organization_funding_query",
 *   label = @Translation("Plan organization funding summary query"),
 *   endpoint = {
 *     "public" = "fts/project/plan",
 *     "version" = "v1",
 *     "query" = {
 *       "planid" = "{plan_id}",
 *       "groupBy" = "organization"
 *     }
 *   }
 * )
 */
class PlanOrganizationFundingQuery extends EndpointQueryBase {

  /**
   * This holds the processed data.
   *
   * @var array
   */
  private $data = NULL;

  /**
   * {@inheritdoc}
   */
  public function getData(array $placeholders = [], array $query_args = []) {
    $placeholders = array_merge($placeholders, $this->getPlaceholders());
    $cache_key = $this->getCacheKey($placeholders);
    if ($cached_data = $this->getCache($cache_key)) {
      return $cached_data;
    }
    $data = (object) parent::getData($placeholders, $query_args);
    if (empty($data->report3) && empty($data->requirements)) {
      return [];
    }
    $funding = [];
    foreach ($data->report3->fundingTotals->objects[0]->singleFundingObjects ?? [] as $organization_funding) {
      if (!property_exists($organization_funding, 'id')) {
        continue;
      }
      $funding[$organization_funding->id] = [
        'total_funding' => $organization_funding->totalFunding,
      ];
    }
    foreach ($data->requirements->objects as $organization_requirements) {
      $organization_id = $organization_requirements->id;
      if (empty($funding[$organization_id])) {
        $funding[$organization_id] = [
          'total_funding' => 0,
        ];
      }
      $funding[$organization_id]['current_requirements'] = $organization_requirements->revisedRequirements ?: 0;
      $funding[$organization_id]['original_requirements'] = $organization_requirements->origRequirements ?: 0;
      $funding[$organization_id]['coverage'] = $organization_requirements->revisedRequirements > 0 ? (100 / $organization_requirements->revisedRequirements) * $funding[$organization_id]['total_funding'] : 0;
    }

    $this->data = $funding;
    $this->setCache($cache_key, $this->data);
    return $this->data;
  }

  /**
   * Get a specific property for an organization from the current result set.
   *
   * @param string $property
   *   The property to retrieve.
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organization for which to retrieve the property value.
   * @param mixed $default
   *   A default value.
   *
   * @return mixed
   *   The retrieved property value or a default value.
   */
  public function getPropertyForOrganization($property, Organization $organization, $default = 0) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    if (empty($this->data[$organization->id])) {
      return $default;
    }
    return !empty($this->data[$organization->id][$property]) ? $this->data[$organization->id][$property] : $default;
  }

  /**
   * Get the requirements changes for an organization from the result set.
   *
   * @param \Drupal\ghi_plans\ApiObjects\Organization $organization
   *   The organizations for which to retrieve the requirements changes.
   *
   * @return mixed
   *   The retrieved property value sum or a default value.
   */
  public function getRequirementsChangesForOrganization(Organization $organization) {
    if (empty($this->data)) {
      $this->data = $this->getData();
    }
    $original_requirements = $this->getPropertyForOrganization('original_requirements', $organization);
    $current_requirements = $this->getPropertyForOrganization('current_requirements', $organization);
    return $current_requirements - $original_requirements;
  }

}
