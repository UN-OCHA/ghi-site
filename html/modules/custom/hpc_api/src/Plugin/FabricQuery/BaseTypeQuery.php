<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'base_type' fabric query.
 */
#[FabricQuery(
  id: 'base_type',
  label: new TranslatableMarkup('Base type query'),
)]
class BaseTypeQuery extends FabricQueryBase {

  /**
   * Retrieve the base types.
   *
   * @return array
   *   An array of arrays, keyed by the query key for the base type, the values
   *   are arrays of result objects.
   */
  public function getBaseTypes(): array {
    $this->fetchBaseTypes();
    return $this->baseTypes;
  }

}
