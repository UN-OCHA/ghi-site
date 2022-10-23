<?php

namespace Drupal\ghi_plans\Plugin\EndpointQuery;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\ghi_plans\Traits\PlanVersionArgument;
use Drupal\hpc_api\Query\EndpointQueryBase;
use Drupal\hpc_api\Traits\SimpleCacheTrait;

/**
 * Provides a query plugin for organizations.
 *
 * @EndpointQuery(
 *   id = "organization_query",
 *   label = @Translation("Organization query"),
 *   endpoint = {
 *     "api_key" = "organization/{organization_id}",
 *     "version" = "v2",
 *   }
 * )
 */
class OrganizationQuery extends EndpointQueryBase {

  use PlanVersionArgument;
  use SimpleCacheTrait;
  use StringTranslationTrait;

  /**
   * Get the base data for a plan.
   *
   * @param int $organization_id
   *   The organization id.
   *
   * @return \Drupal\ghi_plans\ApiObjects\Organization|null
   *   An organization object or NULL.
   */
  public function getOrganization($organization_id) {
    $cache_key = $this->getCacheKey([
      'organization_id' => $organization_id,
    ]);
    $organization = $this->cache($cache_key);
    if ($organization !== NULL) {
      return $organization;
    }
    $data = $this->getData(['organization_id' => $organization_id]);
    $organization = !empty($data) ? new Organization($data) : NULL;

    $this->cache($cache_key, $organization);
    return $organization;
  }

}
