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
    return $this->fabricClient->createQuery('locations', Country::GRAPHQL_DIMENSION_ITEMS)
      ->setFilter('AdminLevel', 0)
      ->setOrderBy(['Name' => 'ASC'])
      ->execute();
  }

}
