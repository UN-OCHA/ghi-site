<?php

namespace Drupal\hpc_api\Plugin\FabricQuery;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hpc_api\Attribute\FabricQuery;
use Drupal\hpc_api\Query\FabricQueryBase;

/**
 * Plugin implementation of the 'entity_type' fabric query.
 *
 * This is mostly here to provide access to the methods of FabricQUeryBase.
 * Having this in a separate file permits to allow consumers to use a
 * semantically annotated query plugin that can be extended further in the
 * future if necessary.
 */
#[FabricQuery(
  id: 'entity_type',
  label: new TranslatableMarkup('Entity type query'),
)]
class EntityTypeQuery extends FabricQueryBase {

}
