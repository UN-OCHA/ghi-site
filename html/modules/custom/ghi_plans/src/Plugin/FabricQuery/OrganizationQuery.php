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
  public function getOrganization($organization_id): ?Organization {
    $payload = "
      organizations (filter:  {
        Id:  {
          eq: {$organization_id}
        }
      }) {
        items { " . Organization::GRAPHQL_DIMENSION_ITEMS . " }
      }";
    $data = $this->fabricQuery->query($payload);
    $items = $data->organizations->items;
    return count($items) == 1 ? new Organization($items[0]) : NULL;
  }

}
