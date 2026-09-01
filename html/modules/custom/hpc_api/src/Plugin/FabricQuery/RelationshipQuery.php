<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'relationship' fabric query.
 */
#[FabricQuery(
  id: 'relationship',
  label: new TranslatableMarkup('Relationship query'),
)]
class RelationshipQuery extends FabricQueryBase {

}
