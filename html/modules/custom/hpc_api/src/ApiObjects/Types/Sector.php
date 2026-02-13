<?php

namespace Drupal\hpc_api\ApiObjects\Types;

use Drupal\hpc_api\ApiObjects\Type;

/**
 * Class for sector objects.
 */
class Sector extends Type {

  const GRAPHQL_ITEMS = ['Id', 'Name', 'SectorType', 'SectorCode'];

}
