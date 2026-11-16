<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_plans\ApiObjects\Organization;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'organization' fabric query.
 */
#[FabricQuery(
  id: 'organization',
  label: new TranslatableMarkup('Organization query'),
)]
class OrganizationQuery extends FabricQueryBase {

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

}
