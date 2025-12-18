<?php

namespace Drupal\ghi_base_objects\Plugin\FabricQuery\Import;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ghi_base_objects\ApiObjects\Country;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;
use Drupal\hpc_api\Query\ImportQueryInterface;

/**
 * Plugin implementation of the 'country_import' fabric query.
 */
#[FabricQuery(
  id: 'country_import',
  label: new TranslatableMarkup('Country import query'),
)]
class CountryImportQuery extends FabricQueryBase implements ImportQueryInterface {

  /**
   * {@inheritdoc}
   */
  public function getSourceData() {
    $payload = '
      locations (
        filter: { AdminLevel: { eq: 0 } }
        first: 10000,
        orderBy: { Name: ASC }
      ) {
        items { ' . Country::GRAPHQL_ITEMS . ' }
      }';
    $data = $this->fabricQuery->query($payload);
    return $data->locations->items;
  }

}
