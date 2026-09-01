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
   * Get the base type defintions.
   *
   * @return array
   *   An array mapping the graphql query name to the responsible class.
   */
  public function getBaseTypeDefinitions(): array {
    return self::BASE_TYPES;
  }

}
