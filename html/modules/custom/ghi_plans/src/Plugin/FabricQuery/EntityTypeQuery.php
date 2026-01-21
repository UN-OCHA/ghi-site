<?php

namespace Drupal\ghi_plans\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'plan' fabric query.
 */
#[FabricQuery(
  id: 'entity_type',
  label: new TranslatableMarkup('Entity type query'),
)]
class EntityTypeQuery extends FabricQueryBase {

}
